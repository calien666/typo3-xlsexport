<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Controller;

use Calien\Xlsexport\Traits\ExportWithTsSettingsTrait;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 *
 *
 * @package xlsexport
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class XlsexportController extends ActionController
{
    use ExportWithTsSettingsTrait;

    /**
     * @var Dispatcher
     * @api
     */
    protected $signalSlotDispatcher;

    /**
     * @var array
     */
    protected $selfSettings = [];
    /**
     * @var array
     */
    private $modTSconfig;

    /**
     * @var object|ConnectionPool
     */
    protected $dbConnection;

    /**
     * XlsexportController constructor.
     */
    public function initializeObject()
    {
        $this->dbConnection = GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * action index
     *
     * @return void
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function indexAction()
    {
        $currentId = $GLOBALS['_GET']['id'];

        if ($currentId == 0 || is_null($currentId)) {
            $this->view->assign('id', $currentId);
        } else {
            $datasets = [];

            $this->loadTSconfig((int)$currentId);

            $hookArray = [];
            if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'])) {
                $hookArray = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'];
            }

            if (is_array($this->selfSettings['exports.'])) {
                foreach ($this->selfSettings['exports.'] as $key => $config) {
                    $keyWithoutDot = str_replace('.', '', $key);
                    if (strlen($config['check']) > 20) {
                        $table = $config['table'];
                        $checkQuery = $config['check'];
                        if (array_key_exists($table, $hookArray) && is_array($hookArray[$keyWithoutDot])) {
                            foreach ($hookArray[$keyWithoutDot] as $classObj) {
                                $hookObj = GeneralUtility::makeInstance($classObj);
                                if (method_exists($hookObj, 'alternateCheckQuery')) {
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

                        $datasets[$keyWithoutDot]['label'] = $config['label'] ? $config['label'] : $table;
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
     * @param string $config
     * @param null $value
     *
     * @return string
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportAction($config, $value = null): string
    {
        $currentId = (int)$GLOBALS['_GET']['id'];

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

        $this->response->setHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->response->setHeader(
            'Content-Disposition',
            sprintf(
                'attachment;filename="%s_%s_%d.xls"',
                date('Y-m-d-His'),
                $settings['table'],
                $currentId
            )
        );
        $this->response->setHeader(
            'Cache-Control',
            'max-age=0'
        );
        return $file->getContents();
    }

    /**
     * @return array
     */
    public function getSelfSettings()
    {
        return $this->selfSettings;
    }

    /**
     * @return mixed
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return array
     */
    public function getCols()
    {
        return $this->cols;
    }
}
