
define(['jquery','TYPO3/CMS/Backend/Utility'],function($,Icons) {

  'use strict';
  var Xlsexport = {
    options: {
      containerSelector: '#sudhaus7-sudhaus7solr-controller-indexcontroller',
            menuItemSelector: '.dropdown-menu li a',
            toolbarIconSelector: '.dropdown-toggle span.icon'
    }
  };

  Xlsexport.initializeEvents = function() {
  };

  Xlsexport.workIndexer = function (ajaxUrl) {
    $.ajax({
      url: ajaxUrl,
      type: 'post',
      cache: false,
      success: function(data) {

      }
    });
  };

  $(function() {
        Xlsexport.initializeEvents();
    });
    TYPO3.Xlsexport = Xlsexport;
    return Xlsexport;
});
