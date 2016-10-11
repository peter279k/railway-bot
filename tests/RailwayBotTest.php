<?php
use railway\RailWayBot\RailWayBot;
use org\bovigo\vfs\vfsStream;
use Mockery as m;


class RailwayBotTest extends \PHPUnit_Framework_TestCase
{
	private $root;
	private $headers;
    private $jsonSettingsFile;
    private $wrongSettingsFile;

    public $railwayBot;

    public function setUp()
    {
    	date_default_timezone_set('America/New_York');
    	$this->root = vfsStream::setup('home');
        $this->jsonSettingsFile = vfsStream::url('home/settings.json');
        $this->wrongSettingsFile = vfsStream::url('home/wrong.txt');
        $file = fopen($this->jsonSettingsFile, 'a');
        fwrite($file, '{');
        fwrite($file, '"name"'.':'.'"Chijioke",');
        fwrite($file, '"age"'.':'.'"23",');
        fwrite($file, '"country"'.':'.'"Nigeria",');
        fwrite($file, '"gender"'.':'.'"Male"');
        fwrite($file, '}');
        fclose($file);

        $this->headers = [
            'Host' => 'railway.hinet.net',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en,en-US;q=0.7,zh-TW;q=0.3',
            'Referer' => 'http://railway.hinet.net/check_ctno1.htm',
            'Connection' => 'keep-alive',
        ];
        
        $this->railwayBot = new RailWayBot($this->jsonSettingsFile);
    }

    public function testCheckCTNNumberFailsToFindTheSettingsFile() {
    	$newBot = new RailWayBot('/file/does/not/exist');

    	$this->assertEquals($newBot->checkCtnnumber(), 'setting file not found');
    }

    public function testSettingsFileDoesNotContainAnArray() {
    	$wrongFile = fopen($this->wrongSettingsFile, 'a');
        fwrite($wrongFile, "This is just a string in the txt file");
        fclose($wrongFile);

        $newBot = new RailWayBot($this->wrongSettingsFile);

    	$this->assertEquals($newBot->checkCtnnumber(), "the setting file is not a JSON format\n");
    }
    
}