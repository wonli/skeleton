<?php

namespace app\cli\controllers;

use app\cli\views\ModelView;

use Cross\Exception\CoreException;
use Cross\Core\Loader;
use Cross\MVC\Module;
use Exception;

/**
 * 从数据库生成结构类
 *
 * Class Property
 * @package app\cli\controllers
 * @property ModelView $view
 */
class Model extends Cli
{
    /**
     * @var ModelView
     */
    protected $view;

    /**
     * 命名空间前缀
     *
     * @var string
     */
    protected $namespacePrefix;

    /**
     * Model constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->view = new ModelView();
    }

    /**
     * 生成结构体
     *
     * @cp_params file=main
     * @param string $name
     * @throws CoreException
     */
    function index($name = '')
    {
        $fileName = &$this->params['file'];
        $configName = "config::{$fileName}.model.php";
        $propertyFile = $this->getFilePath($configName);
        if (!file_exists($propertyFile)) {
            $this->consoleMsg("是否生成配置文件 {$configName} (y/n) - ", false);
            $response = trim(fgetc(STDIN));
            if (0 === strcasecmp($response, 'y')) {
                //生成配置文件
                $ret = $this->view->makeModelFile($propertyFile);
                if (!$ret) {
                    $this->consoleMsg('创建配置文件失败');
                    return;
                }
            } else {
                $this->consoleMsg('请先创建配置文件');
                return;
            }
        }

        $propertyConfig = Loader::read($propertyFile);
        if (!empty($name)) {
            if (!isset($propertyConfig[$name])) {
                $this->consoleMsg("未发现指定的配置{$name}");
                return;
            }

            $this->makeModels($propertyConfig[$name]);
        } elseif (!empty($propertyConfig)) {
            foreach ($propertyConfig as $name => $config) {
                $this->makeModels($config);
            }
        }
    }

    /**
     * @see index
     *
     * @param string $name 指定参数
     * @param array $params
     * @throws CoreException
     * @cp_params file=main
     */
    function __call($name, $params)
    {
        $this->index($name);
    }

    /**
     * 生成model类
     *
     * @param array $config
     * @throws CoreException
     */
    private function makeModels($config)
    {
        if (!empty($config)) {
            $db = &$config['db'];
            if (empty($db)) {
                $this->consoleMsg('请指定数据库链接配置');
                return;
            }

            if (empty($config['type'])) {
                $this->consoleMsg('请指定生成类型 class或trait');
                return;
            }

            if (empty($config['namespace'])) {
                $this->consoleMsg('请指定类的命名空间');
                return;
            }

            $this->namespacePrefix = str_replace('/', '\\', $config['namespace']);
            if (!empty($config['models'])) {
                foreach ($config['models'] as $modelName => $tableNameConfig) {
                    $this->genClass($tableNameConfig, $modelName, $db, $config['type']);
                }
            }
        }
    }

    /**
     * 生成类
     *
     * @param string $tableNameConfig
     * @param string $modelName
     * @param string $db
     * @param string $propertyType 生成类的类型
     * @param array $tableConfig
     * @throws CoreException
     */
    private function genClass($tableNameConfig, $modelName, $db = '', $propertyType = 'class', $tableConfig = array())
    {
        if (empty($db)) {
            $key = ':';
        } else {
            $key = $db;
        }

        static $cache;
        if (!isset($cache[$key])) {
            $cache[$key] = new Module($db);
        }

        $allowPropertyType = array('class' => true, 'trait' => true);
        if (!isset($allowPropertyType[$propertyType])) {
            $propertyType = 'class';
        }

        /* @var $M Module */
        $M = &$cache[$key];
        $linkType = $M->getLinkType();
        $linkName = $M->getLinkName();

        $modelName = str_replace('/', '\\', $modelName);
        $modelName = trim($modelName, '\\');
        $pos = strrpos($modelName, '\\');

        if ($pos) {
            $modelName = substr($modelName, $pos + 1);
            $namespace = substr($modelName, 0, $pos);
            if ($this->namespacePrefix) {
                $namespace = $this->namespacePrefix . '\\' . $namespace;
            }
        } else {
            $namespace = $this->namespacePrefix;
        }

        if (empty($namespace)) {
            $this->consoleMsg("请为 {$propertyType}::{$modelName} 指定命名空间");
            return;
        }

        try {
            if (is_array($tableNameConfig)) {
                $method = &$tableNameConfig['method'];
                if (null === $method) {
                    $method = 'hash';
                }

                $field = &$tableNameConfig['field'];
                if (null === $field) {
                    throw new CoreException('请指定分表字段: field');
                }

                $prefix = &$tableNameConfig['prefix'];
                if (null === $prefix) {
                    throw new CoreException('请指定分表前缀: prefix');
                }

                $number = &$tableNameConfig['number'];
                if (null === $number) {
                    $number = 32;
                } elseif (!is_numeric($number) || $number > 2048) {
                    throw new CoreException('分表数量仅支持数字且不能大于2048！');
                }

                $data['split_info'] = [
                    'number' => $number,
                    'method' => $method,
                    'field' => $field,
                    'prefix' => $prefix,
                ];
                //分表时默认使用第一张表的结构
                $tableName = $tableNameConfig['prefix'] . '0';
            } else {
                $data['split_info'] = [];
                $tableName = $tableNameConfig;
            }

            $mateData = $M->link->getMetaData($M->getPrefix($tableName));
            if (isset($field) && !isset($mateData[$field])) {
                throw new CoreException('分表字段不存在: ' . $field);
            }

            $primaryKey = &$tableConfig['primary_key'];
            if (empty($primaryKey)) {
                foreach ($mateData as $key => $value) {
                    if ($value['primary'] && empty($primaryKey)) {
                        $primaryKey = $key;
                        break;
                    }
                }
            }

            $data['type'] = $propertyType;
            $data['name'] = $modelName;
            $data['mate_data'] = $mateData;
            $data['namespace'] = $namespace;
            $data['model_info'] = [
                'mode' => $linkType . ':' . $linkName,
                'table' => $tableName,
                'primary_key' => $primaryKey,
                'link_type' => $linkType,
                'link_name' => $linkName,
            ];

            $ret = $this->view->genClass($data);
            if (false === $ret) {
                throw new CoreException("请检查目录权限");
            } else {
                $this->consoleMsg("{$propertyType}::{$namespace}\\{$modelName} [成功]");
            }

        } catch (Exception $e) {
            $this->consoleMsg("{$propertyType}::{$namespace}\\{$modelName} [失败 : !! " . $e->getMessage() . ']');
        }
    }
}
