<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Calien - XLS Exporter',
    'description' => 'Excel exporter, fully configurable for every table',
    'category' => 'module',
    'author' => 'Markus Hofmann & Frank Berger',
    'author_email' => 'typo3@calien.de',
    'author_company' => '',
    'state' => 'beta',
    'clearCacheOnLoad' => 0,
    'version' => '3.1.4',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Calien\\Xlsexport\\' => 'Classes',
            'ZipStream\\' => 'vendor/maennchen/zipstream-php/src',
            'Symfony\\Polyfill\\Mbstring\\' => 'vendor/symfony/polyfill-mbstring',
            'Psr\\SimpleCache\\' => 'vendor/psr/simple-cache/src',
            'Psr\\Http\\Message\\' => 'vendor/psr/http-message/src',
            'Psr\\Http\\Client\\' => 'vendor/psr/http-client/src',
            'PhpOffice\\PhpSpreadsheet\\' => 'vendor/phpoffice/phpspreadsheet/src',
            'MyCLabs\\Enum\\' => 'vendor/myclabs/php-enum/src',
            'Matrix\\' => 'vendor/markbaker/matrix/classes/src',
            'Complex\\' => 'vendor/markbaker/complex/classes/src',
        ],
    ],
];
