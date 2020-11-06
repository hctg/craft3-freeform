<?php
/**
 * Freeform for Craft
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2020, Solspace, Inc.
 * @link          https://docs.solspace.com/craft/freeform
 * @license       https://docs.solspace.com/license-agreement
 */

namespace Solspace\Freeform\Services;

use craft\mail\Message;
use craft\web\View;
use Solspace\Commons\Helpers\StringHelper;
use Solspace\Freeform\Elements\Submission;
use Solspace\Freeform\Events\Mailer\RenderEmailEvent;
use Solspace\Freeform\Events\Mailer\SendEmailEvent;
use Solspace\Freeform\Fields\HtmlField;
use Solspace\Freeform\Fields\Pro\RichTextField;
use Solspace\Freeform\Fields\Pro\SignatureField;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Composer\Components\FieldInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\FileUploadInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\NoStorageInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\PaymentInterface;
use Solspace\Freeform\Library\Composer\Components\Form;
use Solspace\Freeform\Library\Exceptions\FreeformException;
use Solspace\Freeform\Library\Logging\FreeformLogger;
use Solspace\Freeform\Library\Mailing\MailHandlerInterface;
use Solspace\Freeform\Library\Mailing\NotificationInterface;
use Twig\Error\LoaderError as TwigLoaderError;
use Twig\Error\SyntaxError as TwigSyntaxError;

class MailerService extends BaseService implements MailHandlerInterface
{
    const EVENT_BEFORE_SEND   = 'beforeSend';
    const EVENT_AFTER_SEND    = 'afterSend';
    const EVENT_BEFORE_RENDER = 'beforeRender';

    const LOG_CATEGORY = 'freeform_notifications';

    /**
     * Send out an email to recipients using the given mail template
     *
     * @param Form             $form
     * @param array|string     $recipients
     * @param mixed            $notificationId
     * @param FieldInterface[] $fields
     * @param Submission       $submission
     *
     * @return int - number of successfully sent emails
     * @throws FreeformException
     */
    public function sendEmail(
        Form $form,
        $recipients,
        $notificationId,
        array $fields,
        Submission $submission = null
    ): int {
        $logger        = FreeformLogger::getInstance(FreeformLogger::MAILER);
        $sentMailCount = 0;
        $notification  = $this->getNotificationById($notificationId);

        if (!\is_array($recipients)) {
            $recipients = $recipients ? [$recipients] : [];
        }

        $previousRecipients = $recipients;
        $recipients         = [];
        foreach ($previousRecipients as $index => $value) {
            $exploded = explode(',', $value);
            foreach ($exploded as $emailString) {
                //$recipients[] = trim($emailString);
                $recipients[] = htmlspecialchars_decode(trim($emailString));
            }
        }

        if (!$notification) {
            $logger = Freeform::getInstance()->logger->getLogger(FreeformLogger::EMAIL_NOTIFICATION);
            $logger->warning(
                Freeform::t(
                    'Email notification template with ID {id} not found',
                    ['id' => $notificationId]
                ),
                ['form' => $form->getName()]
            );

            return 0;
        }

        $fieldValues = $this->getFieldValues($fields, $form, $submission);
        $renderEvent = new RenderEmailEvent($form, $notification, $fieldValues, $submission);

        $this->trigger(self::EVENT_BEFORE_RENDER, $renderEvent);
        $fieldValues = $renderEvent->getFieldValues();

        $templateMode = \Craft::$app->view->getTemplateMode();
        \Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        foreach ($recipients as $recipientName => $emailAddress) {
            $fromName  = $this->renderString(\Craft::parseEnv($notification->getFromName()), $fieldValues);
            $fromEmail = $this->renderString(\Craft::parseEnv($notification->getFromEmail()), $fieldValues);

            $email = new Message();

            try {
                $email->variables = $fieldValues;

                $text    = $this->renderString($notification->getBodyText(), $fieldValues);
                $html    = $this->renderString($notification->getBodyHtml(), $fieldValues);
                $subject = $this->renderString($notification->getSubject(), $fieldValues);
                $subject = htmlspecialchars_decode($subject, ENT_QUOTES);

                $email
                    ->setTo([$emailAddress])
                    ->setFrom([$fromEmail => $fromName])
                    ->setSubject($subject);

                if (empty($text)) {
                    $email
                        ->setHtmlBody($html)
                        ->setTextBody($html);
                }
                if (empty($html)) {
                    $email->setTextBody($text);
                } else {
                    $email
                        ->setHtmlBody($html)
                        ->setTextBody($text);
                }

                if ($notification->getCc()) {
                    $cc = $this->renderString($notification->getCc(), $fieldValues);
                    $cc = StringHelper::extractSeparatedValues($cc);
                    if (!empty($cc)) {
                        $email->setCc($this->parseEnvInArray($cc));
                    }
                }

                if ($notification->getBcc()) {
                    $bcc = $this->renderString($notification->getBcc(), $fieldValues);
                    $bcc = StringHelper::extractSeparatedValues($bcc);
                    if (!empty($bcc)) {
                        $email->setBcc($this->parseEnvInArray($bcc));
                    }
                }

                if ($notification->getReplyToEmail()) {
                    $replyTo = trim($this->renderString(\Craft::parseEnv($notification->getReplyToEmail()), $fieldValues));
                    if (!empty($replyTo)) {
                        $email->setReplyTo($replyTo);
                    }
                }
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $message = 'Email notification [' . $notification->getHandle() . ']: ' . $message;

                $logger->error($message);
                continue;
            }

            $presetAssets = $notification->getPresetAssets();
            if ($presetAssets && is_array($presetAssets) && Freeform::getInstance()->isPro()) {
                foreach ($presetAssets as $assetId) {
                    $asset = \Craft::$app->assets->getAssetById((int) $assetId);
                    if ($asset) {
                        $email->attach($asset->getCopyOfFile());
                    }
                }
            }

            if ($submission && $notification->isIncludeAttachmentsEnabled()) {
                foreach ($fields as $field) {
                    if (!$field instanceof FileUploadInterface || !$field->getHandle()) {
                        continue;
                    }

                    $fieldValue = $submission->{$field->getHandle()}->getValue();
                    $assetIds   = $fieldValue;
                    foreach ($assetIds as $assetId) {
                        $asset = \Craft::$app->assets->getAssetById((int) $assetId);
                        if ($asset) {
                            $email->attach($asset->getCopyOfFile());
                        }
                    }
                }
            }

            try {
                $sendEmailEvent = new SendEmailEvent($email, $form, $notification, $fieldValues, $submission);
                $this->trigger(self::EVENT_BEFORE_SEND, $sendEmailEvent);

                if (!$sendEmailEvent->isValid) {
                    continue;
                }

                $emailSent = \Craft::$app->mailer->send($email);

                $this->trigger(self::EVENT_AFTER_SEND, $sendEmailEvent);

                if ($emailSent) {
                    $sentMailCount++;
                }
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $message = 'Email notification [' . $notification->getHandle() . ']: ' . $message;

                $logger->error($message);
            }
        }

        \Craft::$app->view->setTemplateMode($templateMode);

        return $sentMailCount;
    }

    /**
     * @param int $id
     *
     * @return NotificationInterface|null
     */
    public function getNotificationById($id)
    {
        return Freeform::getInstance()->notifications->getNotificationById($id);
    }

    /**
     * @param array $array
     *
     * @return array
     */
    private function parseEnvInArray(array $array)
    {
        $parsed = [];
        foreach ($array as $key => $item) {
            $parsed[$key] = \Craft::parseEnv($item);
        }

        return $parsed;
    }

    /**
     * @param FieldInterface[] $fields
     * @param Form             $form
     * @param Submission       $submission
     *
     * @return array
     */
    private function getFieldValues(array $fields, Form $form, Submission $submission = null): array
    {
        $postedValues    = [];
        $usableFields    = [];
        $fieldsAndBlocks = [];
        $rules           = $form->getRuleProperties();

        foreach ($fields as $field) {
            if ($field instanceof HtmlField || $field instanceof RichTextField) {
                $fieldsAndBlocks[] = $field;
            }

            if ($field instanceof NoStorageInterface
                || $field instanceof FileUploadInterface
                || $field instanceof PaymentInterface
                || $field instanceof SignatureField
            ) {
                continue;
            }

            if ($submission) {
                $field->setValue($submission->{$field->getHandle()}->getValue());
            }

            if ($rules && $rules->isHidden($field, $form)) {
                continue;
            }

            $fieldsAndBlocks[]                 = $field;
            $usableFields[]                    = $field;
            $postedValues[$field->getHandle()] = $field;
        }

        //TODO: offload this call to payments plugin with an event
        if ($submission && $form->getLayout()->getPaymentFields()) {
            $payments                 = Freeform::getInstance()->payments->getPaymentDetails(
                $submission->getId(),
                $submission->getForm()
            );
            $postedValues['payments'] = $payments;
        }

        $postedValues['allFields']          = $usableFields;
        $postedValues['allFieldsAndBlocks'] = $fieldsAndBlocks;
        $postedValues['form']               = $form;
        $postedValues['submission']         = $submission;
        $postedValues['dateCreated']        = new \DateTime();
        $postedValues['token']              = $submission ? $submission->token : null;

        return $postedValues;
    }

    /**
     * Renders a template defined in a string.
     *
     * @param string $template  The source template string.
     * @param array  $variables Any variables that should be available to the template.
     *
     * @return string The rendered template.
     * @throws TwigLoaderError
     * @throws TwigSyntaxError
     */
    public function renderString(string $template, array $variables = []): string
    {
        if (preg_match('/^\$(\w+)$/', $template, $matches)) {
            return \Craft::parseEnv($template);
        }

        return \Craft::$app->view->getTwig()
            ->createTemplate($template)
            ->render($variables);
    }
}
