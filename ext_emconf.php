<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Calien - XLS Exporter',
    'description' => 'Excel exporter, fully configurable for every table',
    'category' => 'module',
    'author' => 'Markus Hofmann & Frank Berger',
    'author_email' => 'typo3@calien.de',
    'author_company' => '',
    'state' => 'beta',
    'version' => '3.1.8',
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
            'ZipStream\\' => 'Resources/Private/Php/maennchen/zipstream-php/src',
            'Symfony\\Polyfill\\Mbstring\\' => 'Resources/Private/Php/symfony/polyfill-mbstring',
            'Psr\\SimpleCache\\' => 'Resources/Private/Php/psr/simple-cache/src',
            'Psr\\Http\\Message\\' => 'Resources/Private/Php/psr/http-message/src',
            'Psr\\Http\\Client\\' => 'Resources/Private/Php/psr/http-client/src',
            'PhpOffice\\PhpSpreadsheet\\' => 'Resources/Private/Php/phpoffice/phpspreadsheet/src',
            'MyCLabs\\Enum\\' => 'Resources/Private/Php/myclabs/php-enum/src',
            'Matrix\\' => 'Resources/Private/Php/markbaker/matrix/classes/src',
            'Complex\\' => 'Resources/Private/Php/markbaker/complex/classes/src',
        ],
    ],
];
