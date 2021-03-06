<?php
/**
 * @author ideaa <ideaa@qq.com>
 * Ctl.php
 */

namespace app\cli\controllers;

use app\cli\views\CtlView;

use Cross\Exception\CoreException;
use Cross\Core\Loader;
use Cross\Core\Helper;
use ReflectionClass;
use Exception;


/**
 * Class Ctl
 * @package app\cli\controllers
 * @property CtlView $view
 */
class Ctl extends Cli
{
    /**
     * @var array
     */
    protected $commandConfig = [
        'class|c' => '生成类名称（请使用小驼峰命名法，第一个字符自动转换为大写）',
        'viewCtl|v' => '是否生成视图控制器，在配置文件控制',
    ];

    /**
     * 命令描述
     *
     * @var array
     */
    protected $commandDesc = [
        '生成控制器和视图控制器 php ctl:[api|admin|web] ctl:api指定使用api配置项',
        '更多信息请查看config/ctl.config.php配置文件'
    ];

    /**
     * @var string
     */
    protected $ctlConfigName = 'config::ctl.config.php';

    /**
     * @param string $name
     * @throws CoreException
     */
    function index($name = '')
    {
        $configName = &$this->ctlConfigName;
        $propertyFile = $this->getFilePath($configName);
        if (!file_exists($propertyFile)) {
            $this->consoleMsg("Create config file {$configName}? (y/n) - ", false);
            $a = trim(fgetc(STDIN));
            if (0 === strcasecmp($a, 'y')) {
                //生成配置文件
                $ret = $this->view->makeModelFile($propertyFile);
                if ($ret) {
                    $this->consoleMsg('Done!');
                    return;
                } else {
                    $this->consoleMsg('Create config file fail!');
                    return;
                }
            } else {
                $this->consoleMsg('Please make ctl config file!');
                return;
            }
        }

        $ctlConfig = Loader::read($propertyFile);
        if (empty($ctlConfig)) {
            $this->consoleMsg('Empty config file!');
            return;
        }

        if (empty($name)) {
            $name = current(array_keys($ctlConfig));
        }

        if (!isset($ctlConfig[$name])) {
            $this->consoleMsg("Config name:{$name} not defined!");
            return;
        }

        $className = $this->command('class', true);
        $config = &$ctlConfig[$name];
        $config['author'] = sprintf('%s <%s>', $this->dev['name'], $this->dev['email']);
        if (!empty($this->oriParams)) {
            $config = array_merge($config, $this->oriParams);
            $checkValue = ['updateNavMenu' => 1, 'makeViewController' => 1, 'makeTpl' => 1];
            foreach ($config as $k => &$v) {
                if (isset($checkValue[$k])) {
                    $v = $this->getBooleanValueFromString($v);
                }
            }
        }

        $this->genClass($className, $ctlConfig[$name]);
    }

    /**
     * @param mixed $name 指定参数
     * @param mixed $params
     * @throws CoreException
     * @see index
     */
    function __call($name, $params)
    {
        $this->index($name);
    }

    /**
     * 生成类
     *
     * @param string $className
     * @param array $config
     * @throws CoreException
     */
    protected function genClass(string $className, array $config)
    {
        if (empty($config['app'])) {
            $this->consoleMsg('Please specified app name!!');
            return;
        }

        //创建控制器
        $controllerName = ucfirst($className);
        $appDir = PROJECT_REAL_PATH . 'app' . DIRECTORY_SEPARATOR . $config['app'] . DIRECTORY_SEPARATOR;
        $controllerFile = $appDir . 'controllers' . DIRECTORY_SEPARATOR . $controllerName . '.php';
        if (file_exists($controllerFile)) {
            $this->consoleMsg("Controller {$controllerName} already exists!");
        } else {
            $config['controllerUse'] = '';
            if (empty($config['extends'])) {
                $config['extends'] = 'Controller';
                $config['controllerUse'] = 'Cross\MVC\Controller';
            }

            $config['controllerName'] = &$controllerName;
            $config['controllerNamespace'] = 'app\\' . $config['app'] . '\\controllers';
            $ret = $this->view->makeController($controllerFile, $config);
            if ($ret) {
                $this->consoleMsg("Make class {$controllerName} done!");
            } else {
                $this->consoleMsg("Make class {$controllerName} fail!");
                return;
            }
        }

        //创建视图控制器
        $tplName = '';
        $createViewCtl = $this->command('viewCtl');
        if (false !== $createViewCtl) {
            $makeViewController = $this->getBooleanValueFromString($createViewCtl);
        } else {
            $makeViewController = &$config['makeViewController'];
        }

        if ($makeViewController) {
            $viewControllerName = "{$controllerName}View";
            $config['viewControllerName'] = $viewControllerName;
            $config['viewControllerNamespace'] = 'app\\' . $config['app'] . '\\views';
            $viewControllerFile = $appDir . 'views' . DIRECTORY_SEPARATOR . $viewControllerName . '.php';

            if (file_exists($viewControllerFile)) {
                $this->consoleMsg("ViewController {$viewControllerName} already exists!");
            } else {

                $config['tplName'] = '';
                $config['viewControllerUse'] = '';
                if (empty($config['viewExtends'])) {
                    $config['viewExtends'] = 'View';
                    $config['viewControllerUse'] = 'Cross\MVC\View';
                }

                //是否生成模版
                if ($config['makeTpl']) {
                    $tplName = "{$className}/index";
                    $config['tplName'] = &$tplName;
                }

                $ret = $this->view->makeViewController($viewControllerFile, $config);
                if ($ret) {
                    $this->consoleMsg("Make class {$viewControllerName} done!");
                } else {
                    $this->consoleMsg("Make class {$viewControllerName} fail!");
                }
            }
        }

        if ($tplName) {
            //配置的模版目录
            $init = Loader::read($appDir . 'init.php');
            $defaultTplDir = &$init['sys']['default_tpl_dir'];
            $tplName = "{$className}/index";
            $tplFile = $viewControllerFile = $appDir . 'templates' . DIRECTORY_SEPARATOR . $defaultTplDir .
                DIRECTORY_SEPARATOR . $className . DIRECTORY_SEPARATOR . 'index.tpl.php';

            Helper::mkfile($tplFile);
            $ret = $this->view->makeTpl($tplFile, $config);
            if ($ret) {
                $this->consoleMsg("Make template {$tplName} done!");
            } else {
                $this->consoleMsg("Make template {$tplName} fail!");
            }
        }

        if (!empty($config['updateNavMenu'])) {
            //添加导航菜单
            $menuData = array(
                'pid' => 0,
                'name' => $controllerName,
                'link' => $className,
                'display' => 1,
            );

            try {
                $menuClass = 'app\\' . $config['app'] . '\supervise\AclModule';
                $rc = new ReflectionClass($menuClass);
                $model = $rc->newInstance();
                $model->saveNavData($menuData);
            } catch (Exception $e) {
                $this->consoleMsg("Update nav menu fail!");
            }
        }
    }
}