<?php

namespace Tiacx\ApiFox\Utilities;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiFoxPusher
{
    // 测试信息
    protected $testInfo = [];

    // 请求信息
    protected $request;

    // 路由信息
    protected $route;

    // 响应信息
    protected $response;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->route = $request->route();
        $this->response = $response;
    }

    /**
     * 获取项目信息
     * @return array
     */
    protected function getProjectInfo(): array
    {
        return [
            'title' => getenv('APP_NAME'),
            'description' => '',
            'version' => '1.0.0',
        ];
    }

    /**
     * 获取测试信息
     * @return array
     */
    protected function getTestInfo(): array
    {
        $traces = array_reverse(debug_backtrace());
        $target = isset($traces[0]['args'][0]) ? $traces[0]['args'][0] : $traces[1]['args'][0];
        $info = array_filter($target, function($x) {
            return strpos($x, '/(Tests') === 0;
        });
        preg_match("/(\w|\\\\)+::\w+/", current($info), $matches);
        if (empty($matches)) return [];
        list($testClass, $testMethod) = explode('::', str_replace('\\\\', '\\', $matches[0]));
        return ApiFoxHelper::getDocComment($testClass, $testMethod);
    }

    /**
     * 获取表单规则
     * @return array
     */
    protected function getRules(): array
    {
        $controller = method_exists($this->route, 'getControllerClass') ? $this->route->getControllerClass() : $this->route->getController();
        $parameters = ApiFoxHelper::getMethodParameters($controller, $this->route->getActionMethod());
        if (isset($parameters['request'])) {
            return app($parameters['request'])->rules();
        } else {
            return [];
        }
    }

    /**
     * 获取表单屬性
     * @return array
     */
    protected function getAttributes(): array
    {
        $controller = method_exists($this->route, 'getControllerClass') ? $this->route->getControllerClass() : $this->route->getController();
        $parameters = ApiFoxHelper::getMethodParameters($controller, $this->route->getActionMethod());
        if (isset($parameters['request'])) {
            return app($parameters['request'])->attributes();
        } else {
            return [];
        }
    }

    /**
     * 获取响应数据
     * @return mixed
     */
    protected function getResponseData()
    {
        if ($this->response instanceof JsonResponse) {
            return $this->response->getData(true);
        } elseif ($this->response->headers->get('content-type') == 'application/json') {
            return json_decode($this->response->getContent(), true);
        } elseif ($this->response->getFile()) {
            return [];
        } else {
            return $this->response->getContent();
        }
    }

    /**
     * 获取Api信息
     * @return array
     */
    protected function getApiInfo(): array
    {
        $info = [];
        $info['summary'] = data_get($this->testInfo, 'apifox.name', $this->testInfo['test'] ?? $this->route->uri);
        $info['x-apifox-folder'] = data_get($this->testInfo, 'apifox.tags', '');
        $info['x-apifox-status'] = 'pending';
        $info['deprecated'] = (bool)data_get($this->testInfo, 'apifox.deprecated', false);
        $info['description'] = data_get($this->testInfo, 'apifox.description', '');
        $info['tags'] = [data_get($this->testInfo, 'apifox.tags', '')];
        $info['parameters'] = [];

        if (data_get($this->testInfo, 'apifox.withHeaders')) {
            $info['parameters'] = ApiFoxHelper::handleParameters($this->request->headers, 'header');
        }

        preg_match_all("/{(\w+)}/", $this->route->uri, $matches);
        if (isset($matches[1]) && !empty($matches[1])) {
            $info['parameters'] = array_merge($info['parameters'], ApiFoxHelper::handleParameters(array_reduce($matches[1], function($carry, $name) {
                $carry[$name] = [''];
                return $carry;
            }), 'path'));
        }

        if ($this->request->query()) {
            $info['parameters'] = array_merge($info['parameters'], ApiFoxHelper::handleParameters($this->request->query(), 'query'));
        }

        if ($this->request->all()) {
            $contentType = $this->request->allFiles() ? 'multipart/form-data' : $this->request->header('content-type');
            $info['requestBody'] = [];
            $info['requestBody']['content'] = [];
            $info['requestBody']['content'][$contentType] = [];
            $info['requestBody']['content'][$contentType]['schema'] = ApiFoxHelper::genSchema($this->request->all(), $this->getRules(), $this->getAttributes());
            if ($this->request->getContent()) {
                $info['requestBody']['content'][$contentType]['example'] = $this->request->getContent();
            }
        }

        $statusCode = $this->response->getStatusCode();
        $contentType = $this->response->headers->get('content-type');
        $responseData = $this->getResponseData();
        if (empty($responseData)) $contentType = 'application/octet-stream';
        $info['responses'] = [];
        $info['responses'][$statusCode] = [];
        $info['responses'][$statusCode]['description'] = $statusCode == 200 ? '成功' :  __('http-statuses.' . $statusCode, [], 'zh_CN');
        $info['responses'][$statusCode]['content'] = [];
        $info['responses'][$statusCode]['content'][$contentType] = [];
        $info['responses'][$statusCode]['content'][$contentType]['schema'] = ApiFoxHelper::genSchema($responseData);
        if (!empty($responseData)) {
            $info['responses'][$statusCode]['content'][$contentType]['examples'] = [
                1 => [
                    'summary' => '示例',
                    'value' => $responseData,
                ]
            ];
        }

        return $info;
    }

    /**
     * 生成 json 数据
     * @see https://apifox-openapi.apifox.cn/
     * @return array
     */
    protected function genJsonData(): array
    {
        $output = [];
        $output['openapi'] = '3.1.0'; // ApiFox 目前仅支持导入 OpenAPI 3、Swagger 1、2、3 等格式数据
        $output['info'] = $this->getProjectInfo();
        $tags = explode('/', data_get($this->testInfo, 'apifox.tags', ''));
        $output['tags'] = array_map(function($i) use ($tags) {
            return ['name' => implode('/', array_slice($tags, 0, $i + 1))];
        }, array_keys($tags));
        $output['paths'] = [];
        $uri = strpos($this->route->uri, '/') === 0 ? $this->route->uri : '/' . $this->route->uri;
        $output['paths'][$uri] = [
            strtolower($this->request->getMethod()) => $this->getApiInfo(),
        ];
        return $output;
    }

    /**
     * 处理
     * @return void
     */
    public function handle()
    {
        $this->testInfo = $this->getTestInfo();
        if (!data_get($this->testInfo, 'apifox.name')) return;

        $projectId = config('apifox.project_id');
        $token = config('apifox.access_token');

        if (empty($projectId) || empty($token)) {
            throw new \Exception('ApiFox 配置错误，请检查配置~');
        }

        $client = new Client([
            'verify' => false,
            'headers' => [
                'X-Apifox-Version' => '2022-11-16', // 固定值
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ],
        ]);

        $response = $client->post("https://api.apifox.cn/api/v1/projects/{$projectId}/import-data", [
            'json' => [
                'importFormat' => 'openapi',
                'data' => $this->genJsonData(),
            ]]);

        if ($response->getStatusCode() == 200) {
            $content = json_decode($response->getBody()->getContents(), true);
            if (data_get($content, 'success') == true) {
                dump("ApiFox: {$this->route->uri} 创建成功！");
            } else {
                dump($content);
            }
        }
    }
}
