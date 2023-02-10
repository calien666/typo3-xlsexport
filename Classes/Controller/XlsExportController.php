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
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class XlsExportController extends ActionController
{
    use ExportWithTsSettingsTrait;

    protected ConnectionPool $dbConnection;

    protected ModuleTemplateFactory $moduleTemplateFactory;

    protected int $pageId = 0;
    /**
     * @deprecated will be removed in future versions
     */
    protected array $hooks = [];

    public function __construct(
        ConnectionPool $connectionPool,
        ModuleTemplateFactory $moduleTemplateFactory
    ) {
        $this->dbConnection = $connectionPool;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->loadHooks();
    }

    /**
     * action index
     * renders the export view
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function indexAction(): ResponseInterface
    {
        $this->pageId = (int)GeneralUtility::_GP('id') ?? 0;
        $this->view->assign('id', $this->pageId);
        if ($this->pageId > 0) {
            $this->loadTSconfig($this->pageId);

            if (
                array_key_exists('exports.', $this->selfSettings)
                && is_array($this->selfSettings['exports.'])
            ) {
                $this->buildDataArrayForListView();
                $this->view->assign('settings', $this->selfSettings);
                $this->addAdditionalData();
            } else {
                $this->view->assign('noconfig', 1);
            }
        }
        return $this->htmlResponse();
    }

    /**
     * action export
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws DBALException
     */
    public function exportAction(int $id, string $config): ResponseInterface
    {
        $this->pageId = $id;

        $this->loadTSconfig($this->pageId);

        $settings = $this->selfSettings['exports.'][$config . '.'];

        $file = $this->doExport($settings, $this->pageId);

        //ins Archiv verschieben
        if ($settings['archive']) {
            $archive = $settings['archive'];

            $dbQuery = $this->dbConnection->getQueryBuilderForTable($settings['table']);
            $dbQuery->update($settings['table'])
                ->where(
                    $dbQuery->expr()->eq('pid', $dbQuery->createNamedParameter($this->pageId, \PDO::PARAM_INT))
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
                    $this->pageId
                )
            )
            ->withHeader('Cache-Control', 'max-age=0')
            ->withBody($file);
    }

    private function buildDataArrayForListView(): void
    {
        $datasets = [];
        $event = $this->eventDispatcher->dispatch(new AlternateCheckQueryEvent($this->selfSettings['exports.']));
        $this->selfSettings['exports.'] = $event->getManipulatedSettings();
        foreach ($this->selfSettings['exports.'] as $key => $config) {
            $keyWithoutDot = str_replace('.', '', $key);
            if (strlen($config['check']) > 20) {
                $table = $config['table'];
                $checkQuery = $config['check'];

                /** @deprecated use PSR-14 event instead, will be removed in future versions */
                if (array_key_exists($table, $this->hooks) && is_array($this->hooks[$keyWithoutDot])) {
                    foreach ($this->hooks[$keyWithoutDot] as $classObj) {
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

                $statement = sprintf($checkQuery, $this->pageId);
                $dbQuery = $this->dbConnection->getQueryBuilderForTable($table)->getConnection();
                $result = $dbQuery->executeQuery($statement)->fetchAllAssociative();

                // if all datasets from this page should be exported
                if (count($result) == 1) {
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
        $this->view->assign('datasets', $datasets);
    }

    private function addAdditionalData(): void
    {
        $additionalData = [];
        if (array_key_exists('additionalData', $this->hooks)) {
            foreach ($this->hooks['additionalData'] as $classObj) {
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

    /**
     * @deprecated Will be removed in future version
     */
    private function loadHooks(): void
    {
        /** @deprecated Use PSR-14 Events instead */
        if (
            array_key_exists('xlsexport', $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'])
            && array_key_exists('alternateQueries', $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'])
        ) {
            $this->hooks = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'];
        }
    }
}
