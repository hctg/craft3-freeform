<?php

namespace Solspace\Freeform\Controllers;

use Solspace\Commons\Helpers\PermissionHelper;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Resources\Bundles\LogBundle;
use yii\web\Response;

class LogsController extends BaseController
{
    public function actionIndex(): Response
    {
        $logReader = $this->getLoggerService()->getLogReader();

        $this->getLoggerService()->registerJsTranslations($this->view);

        return $this->renderTemplate(
            'freeform/logs/index',
            [
                'logReader' => $logReader,
            ]
        );
    }

    public function actionError(): Response
    {
        $logReader = $this->getLoggerService()->getLogReader();

        $this->getLoggerService()->registerJsTranslations($this->view);
        \Craft::$app->view->registerAssetBundle(LogBundle::class);

        return $this->renderTemplate(
            'freeform/logs/error',
            [
                'logReader' => $logReader,
            ]
        );
    }

    /**
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionClear(): Response
    {
        $this->requirePostRequest();
        PermissionHelper::requirePermission(Freeform::PERMISSION_SETTINGS_ACCESS);

        $this->getLoggerService()->clearLogs();

        if (\Craft::$app->request->getIsAjax()) {
            return $this->asJson(['success' => true]);
        }

        return $this->redirect('/');
    }
}
