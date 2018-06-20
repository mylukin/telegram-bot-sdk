<?php

namespace Telegram\Bot\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\LazyOpenStream;
use Telegram\Bot\FileUpload\InputFile;

class InputFileTest extends TestCase
{

    protected $tempPath;
    protected $tempFileResource;
    protected $tempStream;
    protected $tempFileName;
    protected $url;

    public function setUp()
    {
        parent::setUp();
        $this->tempPath = sys_get_temp_dir();
        $this->tempFileName = $this->tempPath . '/TestFile.tmp';
        $this->tempFileResource = fopen($this->tempFileName, 'w+');
        $this->tempStream = new LazyOpenStream($this->tempFileName, 'r');
    }

    public function tearDown()
    {
        if (file_exists($this->tempFileName)) {
            unlink($this->tempFileName);
        }
    }

    /** @test */
    public function it_detects_the_file_name_from_a_stream_or_resource_or_url_or_string()
    {
        $inputFileString = InputFile::create($this->tempFileName);
        $inputFileUrlWithExtension = InputFile::create("http://localhost/remoteFile.tmp");
        $inputFileUrlNoExtension = InputFile::create("http://localhost/uo13nzxcl5014pnSX7DIty16k_H47F_GulRO");
        $inputFileResource = InputFile::create($this->tempFileResource);
        $inputFileStream = InputFile::create($this->tempStream);


        $this->assertEquals('TestFile.tmp', $inputFileString->getFilename());
        $this->assertEquals('remoteFile.tmp', $inputFileUrlWithExtension->getFilename());
        $this->assertEquals('uo13nzxcl5014pnSX7DIty16k_H47F_GulRO', $inputFileUrlNoExtension->getFilename());
        $this->assertEquals('TestFile.tmp', $inputFileResource->getFilename());
        $this->assertEquals('TestFile.tmp', $inputFileStream->getFilename());
    }

    /** @test */
    public function it_overrides_the_original_filename_if_another_filename_is_provided()
    {
        $inputFileString = InputFile::create($this->tempFileName, 'newFileNameString.jpg');
        $inputFileResource = InputFile::create($this->tempFileResource, 'newFileNameResource.jpg');
        $inputFileStream = InputFile::create($this->tempStream, 'newFileNameStream.jpg');

        $this->assertEquals('newFileNameString.jpg', $inputFileString->getFilename());
        $this->assertEquals('newFileNameResource.jpg', $inputFileResource->getFilename());
        $this->assertEquals('newFileNameStream.jpg', $inputFileStream->getFilename());
    }
}