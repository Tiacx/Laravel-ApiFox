Laravel-ApiFox
============

说明
---

功能：通过`TestCase`快速生成`ApiFox`文档

安装
---

```bash
composer require tiacx/laravel-apifox --dev
```

配置
---

发布配置文件

```bash
php artisan vendor:publish --tag=config
```

配置请求中间件

> 修改 `app/Http/Kernel.php` 文件，增加 `\Tiacx\ApiFox\Middleware\ApiFoxMiddleware::class` 中间件配置

增加 `.env` 配置项

```yaml
APIFOX_PROJECT_ID=3481718
APIFOX_ACCESS_TOKEN=APS-8YGmxRP5V3w5dWER6WEXXXXXXXXXX
```

注：项目ID 及 访问令牌的获取方法请参考官方开放平台说明（https://apifox-openapi.apifox.cn/）

使用
---

在 `TestCase` 方法处添加 `@apifox.name` 及 `@apifox.tags` 标识，然后运行 `TestCase` 即可。

+ `@apifox.name` 接口名称（必填）
+ `@apifox.name` 接口描述（非必填）
+ `@apifox.tags` 接口目录（非必填。支持多级，使用 `/` 隔开）
+ `@apifox.withHeaders` 带上头信息（非必填。公共头应该在 `ApiFox` 里设置）

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * @test 测试生成ApiFox文档
     * @apifox.name 这是一个测试接口
     * @apifox.tags Admin/Test
     */
    public function test_laravel_apifox(): void
    {
        $response = $this->getJson('/api/admin/test')
            ->assertStatus(200)
            ->json();
        dd($response);
    }
}
```

注：生成文档之后，可把 `@apifox` 相关注释删除掉。

注意事项
-------

`ApiFox`开放平台只提供的数据导入接口，只有新增接口功能，没有更新或删除接口的功能。如果需要重新推送接口，要先手动删除`ApiFox`里的接口，再运行`TestCase`，再重新推送。
