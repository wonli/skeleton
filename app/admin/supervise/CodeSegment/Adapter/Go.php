<?php
/**
 * @author wonli <wonli@live.com>
 * Flutter.php
 */

namespace app\admin\supervise\CodeSegment\Adapter;

use app\admin\supervise\CodeSegment\Adapter;

class Go extends Adapter
{
    /**
     * 单结构容器
     *
     * @var array
     */
    protected $singleClass;

    /**
     * 防重名
     *
     * @var array
     */
    protected $usedClassName;

    /**
     * @return string
     */
    function gen()
    {
        $code = '';
        $this->doGen($this->struct, $code);

        $f = '';
        if (!empty($this->singleClass)) {
            foreach ($this->singleClass as $s) {
                $f .= "\n" . $s;
            }
        }

        $d = $this->genClass('Result', $code);
        return $d . $f;
    }

    /**
     * @param array $data
     * @param string $code
     * @param string $name
     */
    protected function doGen(array $data, string &$code = '', string $name = 'Result')
    {
        $i = $j = 65;
        $json = [];
        foreach ($data as $n => $tree) {
            if ($tree['type'] == 'properties') {
                $json[] = $tree['segment']['json'];
            } else {
                $pName = $this->toCamelCase($n);
                $className = $this->toCamelCase($n, 'pascal') . 'Model';

                if (isset($this->usedClassName[$pName])) {
                    $pName = $pName . chr($i);
                    $i++;
                } else {
                    $this->usedClassName[$pName] = 1;
                }

                if (isset($this->usedClassName[$className])) {
                    $className = $className . chr($j);
                    $j++;
                } else {
                    $this->usedClassName[$className] = 1;
                }

                $token = $className;
                if ($tree['type'] == 'list') {
                    $token = "[]{$className}";
                }

                $json[] = $this->propertiesToJson($pName, $n, $token);

                $item = '';
                $this->doGen($tree['segment'], $item, $className);

                $this->singleClass[] = $this->genClass($className, $item);
            }
        }

        $code .= $this->fromJsonBlock($name, $json);
    }

    /**
     * fromJson
     *
     * @param string $class
     * @param array $data
     * @return string
     */
    function fromJsonBlock(string $class, array $data)
    {
        if (!empty($data)) {
            $a = '';
            $i = 0;
            array_map(function ($d) use (&$a, &$i) {
                if ($i > 0) {
                    $a .= '    ';
                }

                $a .= "{$d}\n";
                $i++;
            }, $data);

            $a = trim($a, "\n");
            return "\n    {$a}";
        }

        return '';
    }

    /**
     * @param string $token
     * @param string $propertiesName
     * @return mixed
     */
    function makeProperties(string $token, string $propertiesName)
    {

    }

    /**
     * @param string $propertiesName
     * @param string $name
     * @param string $token
     * @return mixed
     */
    function propertiesToJson(string $propertiesName, string $name, string $token = '')
    {
        $propertiesName = ucfirst($propertiesName);
        return "{$propertiesName} {$token} `json:\"{$name}\"`";
    }

    /**
     * @param string $className
     * @param string $classBody
     * @return mixed
     */
    function genClass(string $className, string $classBody)
    {
        return "type {$className} struct {" . $classBody . "\n}\n";
    }

    /**
     * 字段类型
     *
     * @return array
     */
    function getTokens()
    {
        return array(
            'float' => 'float64',
            'int' => 'int',
            'bool' => 'bool',
            'string' => 'string',
        );
    }
}