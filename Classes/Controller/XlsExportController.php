<?php

/**
 * Markus Hofmann
 * 12.10.21 21:33
 * churchevent
 */

declare(strict_types=1);

namespace Calien\Xlsexport\Controller;

use Calien\Xlsexport\Export\Event\AlternateCheckQueryEvent;
use Calien\Xlsexport\Traits\ExportWithTsSettingsTrait;
use Doctrine\DBAL\Driver\Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3Fluid\Fluid\View\ViewInterface;

class XlsExportController
{
    use ExportWithTsSettingsTrait;

    /**
     * @var ModuleTemplate
     */
    protected ModuleTemplate $moduleTemplate;

    /**
     * @var ConnectionPool
     */
    protected ConnectionPool $dbConnection;

    protected ViewInterface $view;
    /**
     * @var EventDispatcherInterface
     */
    protected EventDispatcherInterface $eventDispatcher;

    /**
     * Constructor Method
     *
     *
     * @param ConnectionPool $connectionPool
     * @param ModuleTemplate $moduleTemplate
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        ConnectionPool           $connectionPool,
        ModuleTemplate           $moduleTemplate,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->dbConnection = $connectionPool;
        $this->moduleTemplate = $moduleTemplate;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $action = (string)($request->getQueryParams()['action'] ?? $request->getParsedBody()['action'] ?? 'index');

        /**
         * Define allowed actions
         */
        if (!in_array($action, ['index', 'export'], true)) {
            return new HtmlResponse('Action not allowed', 400);
        }

        /**
         * Configure template paths for your backend module
         */
        $this->view = GeneralUtility::makeInstance(StandaloneView::class);
        $this->view->setTemplateRootPaths(['EXT:xlsexport/Resources/Private/Templates/XlsExport']);
        $this->view->setPartialRootPaths(['EXT:xlsexport/Resources/Private/Partials']);
        $this->view->setLayoutRootPaths(['EXT:xlsexport/Resources/Private/Layouts/']);
        $this->view->setTemplate($action);

        /**
         * Call the passed in action
         */
        $result = $this->{$action . 'Action'}($request);

        if ($result instanceof ResponseInterface) {
            return $result;
        }
        //$pageinfo = BackendUtility::readPageAccess($this->id, $perms_clause);
        //$this->moduleTemplate->getDocHeaderComponent()->setMetaInformation($metaInformation);
        /**
         * Render template and return html content
         */
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * action index
     *
     * @param ServerRequestInterface $request
     * @return void
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function indexAction(ServerRequestInterface $request)
    {
        $currentId = $request->getParsedBody()['id'] ?? $request->getQueryParams()['id'];

        if ($currentId == 0) {
            $this->view->assign('id', $currentId);
        } else {
            $datasets = [];

            $this->loadTSconfig((int)$currentId);

            /** @deprecated Use PSR-14 Events instead */
            $hookArray = [];
            if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'])) {
                $hookArray = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'];
            }

            if (is_array($this->selfSettings['exports.'])) {
                $event = $this->eventDispatcher->dispatch(new AlternateCheckQueryEvent($this->selfSettings['exports.']));
                $this->selfSettings['exports.'] = $event->getManipulatedSettings();
                foreach ($this->selfSettings['exports.'] as $key => $config) {
                    $keyWithoutDot = str_replace('.', '', $key);
                    if (strlen($config['check']) > 20) {
                        $table = $config['table'];
                        $checkQuery = $config['check'];

                        /** @deprecated use PSR-14 event instead, will be removed in future versions */
                        if (array_key_exists($table, $hookArray) && is_array($hookArray[$keyWithoutDot])) {
                            foreach ($hookArray[$keyWithoutDot] as $classObj) {
                                $hookObj = GeneralUtility::makeInstance($classObj);
                                if (method_exists($hookObj, 'alternateCheckQuery')) {
                                    trigger_error(
                                        'Usage of hooks inside XLS export is deprecated and will be removed in future versions. Use PSR-14 Event dispatching instead.',
                                        E_USER_DEPRECATED
                                    );
                                    $checkQuery = $hookObj->alternateCheckQuery($checkQuery, $this);
                                }
                            }
                        }

                        $statement = sprintf($checkQuery, $currentId);
                        $dbQuery = $this->dbConnection->getQueryBuilderForTable($table)->getConnection();
                        $result = $dbQuery->executeQuery($statement)->fetchAllAssociative();

                        // if all datasets from this page should be exported
                        if (sizeof($result) == 1) {
                            $count = $result[0];
                            $datasets[$keyWithoutDot]['count'] = $count['count(uid)'] ?? $count['count(*)'];
                        } else {
                            foreach ($result as $row) {
                                $datasets[$keyWithoutDot]['options'][end($row)]['count'] = $row['count(*)'];
                            }
                        }

                        $datasets[$keyWithoutDot]['label'] = $config['label'] ?: $table;
                        $datasets[$keyWithoutDot]['config'] = $keyWithoutDot;
                    }
                }
            }

            $this->view->assign('id', $currentId);
            $this->view->assign('settings', $this->selfSettings);
            $this->view->assign('datasets', $datasets);
            $additionalData = [];
            if ($currentId > 0) {
                if (array_key_exists('additionalData', $hookArray)) {
                    foreach ($hookArray['additionalData'] as $classObj) {
                        $hookObj = GeneralUtility::makeInstance($classObj);
                        if (method_exists($hookObj, 'addAdditionalData')) {
                            $hookObj->addAdditionalData($additionalData, $this);
                        }
                    }
                }
                if (count($additionalData) > 0) {
                    $this->view->assign('additionalData', $additionalData);
                }
            }
        }
    }

    /**
     * action export
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportAction(ServerRequestInterface $request): ResponseInterface
    {
        $currentId = (int)($request->getParsedBody()['id'] ?? $request->getQueryParams()['id']);
        $config = $request->getParsedBody()['config'] ?? $request->getQueryParams()['config'];
        $value = $request->getParsedBody()['value'] ?? $request->getQueryParams()['value'] ?? null;

        $this->loadTSconfig($currentId);

        $settings = $this->selfSettings['exports.'][$config . '.'];

        if (!is_null($value)) {
            $settings['value'] = $value;
        }
        $file = $this->doExport($settings, $currentId);

        //ins Archiv verschieben
        if ($settings['archive']) {
            $archive = $settings['archive'];

            $dbQuery = $this->dbConnection->getQueryBuilderForTable($settings['table']);
            $dbQuery->update($settings['table'])
                ->where(
                    $dbQuery->expr()->eq('pid', $dbQuery->createNamedParameter($currentId, \PDO::PARAM_INT))
                )
                ->set('pid', $dbQuery->createNamedParameter($archive, \PDO::PARAM_INT))
                ->execute();
        }

        return (new Response())
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader(
                'Content-Disposition',
                sprintf(
                    'attachment;filename="%s_%s_%d.xlsx"',
                    date('Y-m-d-His'),
                    $settings['table'],
                    $currentId
                )
            )
            ->withHeader('Cache-Control', 'max-age=0')
            ->withBody($file);
    }
}
