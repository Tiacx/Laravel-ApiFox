<?php

namespace Tiacx\ApiFox\Utilities;

use ReflectionClass;
use Illuminate\Support\Arr;
use Illuminate\Http\UploadedFile;

class ApiFoxHelper
{
    /**
     * 获取文档注释
     * @return array
     */
    public static function getDocComment(string $className, string $methodName): array
    {
        $refController = new ReflectionClass($className);
        $refMethod = $refController->getMethod($methodName);
        $comments = $refMethod->getDocComment();
        $results = [];
        foreach(explode("\n", $comments) as $line) {
            if (strpos($line, '@') !== false) {
                preg_match("/@([\w.]+)\s*(.+)?/", rtrim($line), $matches);
                $results = Arr::add($results, $matches[1], $matches[2] ?? true);
            }
        }
        return $results;
    }

    /**
     * 获取方法参数
     * @return array
     */
    public static function getMethodParameters($classOrName, string $methodName): array
    {
        $refController = new ReflectionClass($classOrName);
        $refMethod = $refController->getMethod($methodName);
        $parameters = $refMethod->getParameters();
        if (empty($parameters)) return [];
        return array_reduce($parameters, function($carry, $item) {
            $carry[$item->getName()] = $item->getType() ? strval($item->getType()) : 'string';
            return $carry;
        });
    }

    /**
     * 获取变量类型
     * @param mixed $value
     * @return string
     */
    public static function getType($value): string
    {
        $type = gettype($value);

        if ($type == 'double') {
            $type = 'number';
        } elseif ($type == 'resource') {
            $type = 'string';
        } elseif ($type == 'array' && (!empty($value) && !isset($value[0]))) {
            $type = 'object';
        }

        return $type;
    }

    /**
     * 处理请求参数
     * @param iterable $parameters
     * @param string $in
     * @return array
     */
    public static function handleParameters($parameters, string $in): array
    {
        $results = [];
        foreach ($parameters as $name => $value) {
            if (!isset($value[0])) {
                $name = $name . '[' . key($value) . ']';
                $value = [current($value)];
            }
            $results[] = [
                'name' => $name,
                'in' => $in,
                'description' => '',
                'required' => true,
                'example' => $value[0],
                'schema' => ['type' => self::getType($value[0])],
            ];
        }
        return $results;
    }

    /**
     * 合并规则与数据
     * @param array $rules
     * @param array $postData
     * @return array
     */
    public static function mergeRulesAndData(array $rules, array $postData): array
    {
        $rulesData = [];
        foreach ($rules as $key => $value) {
            if (strpos($value, 'integer') !== false) {
                $value = 0;
            } elseif (strpos($value, 'decimal') !== false) {
                $value = 0.01;
            } elseif (strpos($value, 'array') !== false) {
                $value = [];
            } elseif (strpos($value, 'boolean') !== false) {
                $value = true;
            } else {
                $value = '';
            }
            Arr::add($rulesData, $key, $value);
        }
        return array_merge($rulesData, $postData);
    }

    /**
     * 生成Schema信息
     * @param array $data
     * @param array $rules
     * @param array $attributes
     * @return array
     */
    public static function genSchema(array $data, array $rules = [], array $attributes = []): array
    {
        $schema = [];
        $schema['type'] = 'object';
        $data = self::mergeRulesAndData($rules, $data);
        foreach ($data as $key => $value) {
            $type = self::getType($value);
            if ($type == 'object') {
                if ($value instanceof UploadedFile) {
                    $schema['properties'][$key] = [
                        'type' => 'string',
                        'description' => '文件',
                        'format' => 'binary',
                    ];
                } else {
                    $schema['properties'][$key] = self::genSchema($value);
                    $schema['properties'][$key]['title'] = data_get($attributes, $key, '');
                }
                continue;
            }
            $schema['properties'][$key] = [
                'type' => !$rules || strpos($rules[$key] ?? '', 'required') !== false ? $type : [$type, 'null'],
                'title' => data_get($attributes, $key, ''),
            ];
            if ($type == 'array') {
                $schema['properties'][$key]['items'] = [];
                if (!empty($value) && self::getType($value[0]) == 'object') {
                    $schema['properties'][$key]['items'] = self::genSchema($value[0]);
                } else {
                    $schema['properties'][$key]['items']['type'] = !empty($value) ? self::getType($value[0]) : 'string';
                }
            }
        }
        $schema['x-apifox-orders'] = array_keys($data);
        $schema['required'] = array_keys(array_filter($rules, function($item) {
            return strpos($item, 'required') !== false;
        }));
        $schema['x-apifox-ignore-properties'] = [];
        return $schema;
    }
}
