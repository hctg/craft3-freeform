!function(e){var t={};function r(n){if(t[n])return t[n].exports;var o=t[n]={i:n,l:!1,exports:{}};return e[n].call(o.exports,o,o.exports,r),o.l=!0,o.exports}r.m=e,r.c=t,r.d=function(e,t,n){r.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:n})},r.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},r.t=function(e,t){if(1&t&&(e=r(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var n=Object.create(null);if(r.r(n),Object.defineProperty(n,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var o in e)r.d(n,o,function(t){return e[t]}.bind(null,o));return n},r.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return r.d(t,"a",t),t},r.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},r.p="",r(r.s=166)}({166:function(e,t,r){function n(e,t){var r=Object.keys(e);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(e);t&&(n=n.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),r.push.apply(r,n)}return r}function o(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{};t%2?n(Object(r),!0).forEach((function(t){s(e,t,r[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(r)):n(Object(r)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(r,t))}))}return e}function s(e,t,r){return t in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}function i(e){return(i="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}"undefined"===i(Craft.Freeform)&&(Craft.Freeform={});var a=function(){var e=window.defaultFormHandle;if("index"===this.settings.context&&void 0!==e)for(var t=0;t<this.$sources.length;t++){var r=$(this.$sources[t]);if(r.data("handle")===e)return r.data("key")}return this.base()},u=function(){if(this.$source){var e=this.$source.data("handle");if("index"===this.settings.context&&"undefined"!=typeof history){var t=this.baseUrl;e&&(t+="/"+e),history.replaceState({},"",Craft.getUrl(t))}}};Craft.Freeform.SubmissionsIndex=Craft.BaseElementIndex.extend({baseUrl:"freeform/submissions",afterInit:function(){this.on("selectSource",$.proxy(this,"updateButton")),this.on("selectSite",$.proxy(this,"updateButton")),this.base()},getViewClass:function(e){switch(e){case"table":return Craft.Freeform.SubmissionsTableView;default:return this.base(e)}},getDefaultSort:function(){return["dateCreated","desc"]},getDefaultSourceKey:a,updateButton:u}),Craft.Freeform.SpamSubmissionsIndex=Craft.BaseElementIndex.extend({baseUrl:"freeform/spam",reasonContainer:null,reasonMenuBtn:null,selectedReason:null,getDefaultSourceKey:a,afterInit:function(){var e=this;this.reasonContainer=$("<div></div>"),this.reasonContainer.append($("#spam-reasons").html()),this.reasonMenuBtn=$(".btn.menubtn",this.reasonContainer),this.$statusMenuContainer.before(this.reasonContainer),$("*[data-reason]",this.reasonContainer).on({click:function(t){var r=t.target,n=$(r).data("reason"),s=$(r).text();$(r).addClass("sel").parent().siblings().find("a").removeClass("sel"),e.reasonMenuBtn.text(s),e.selectedReason=n,e.settings.criteria=o(o({},e.settings.criteria),{},{spamReason:n}),e.updateElements()}}),this.on("selectSource",$.proxy(this,"updateButton")),this.on("selectSite",$.proxy(this,"updateButton")),this.base()},updateButton:u}),Craft.registerElementIndexClass("Solspace\\Freeform\\Elements\\Submission",Craft.Freeform.SubmissionsIndex),Craft.registerElementIndexClass("Solspace\\Freeform\\Elements\\SpamSubmission",Craft.Freeform.SpamSubmissionsIndex)}});