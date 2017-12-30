<?php

namespace Droath\ProjectX\Tests\Engine\DockerServices;

use Droath\ProjectX\Engine\DockerService;
use Droath\ProjectX\Engine\DockerServices\ApacheService;
use Droath\ProjectX\Tests\TestBase;

class ApacheServiceTest extends TestBase
{
    protected $service;
    protected $classname;

    public function setUp() {
        parent::setUp();
        $this->service = new ApacheService();
        $this->classname = ApacheService::class;
    }

    public function testName()
    {
        $this->assertEquals('apache', $this->classname::name());
    }

    public function testGroup()
    {
        $this->assertEquals('frontend', $this->classname::group());
    }

    public function testService()
    {
        $service = $this->service->service();
        $this->assertEquals([
            'image'       => 'httpd:2.4',
            'ports'       => ['80:80'],
            'volumes'     => [
                './:/var/www/html',
                './docker/services/apache/httpd.conf:/usr/local/apache2/conf/httpd.conf'
            ],
        ], $service->asArray());
    }

    public function testTemplateFiles()
    {
        $this->assertEquals([
            'httpd.conf' => [
                'variables' => [
                    'PHP_SERVICE' => 'php'
                ],
                'overwrite' => true,
            ]], $this->service->templateFiles());
    }
}
