<?php
namespace App\Utils;

use XMLWriter;

class XmlUtils {
    protected $tmpFileLocation;
    protected $startElement = null;
    protected $xmlWriter;

    public function __construct()
    {
        $this->create();
    }

    public function create(){
        $this->tmpFileLocation = sys_get_temp_dir(). '/xml_' . uniqid() .rand(1, 9999);

        $this->xmlWriter = new XMLWriter();
		$this->xmlWriter->openURI($this->tmpFileLocation);
		$this->xmlWriter->setIndent(false);
        // $this->xmlWriter->startDocument();
    }

    public function openEle(string $name): XmlUtils{
        $this->xmlWriter->startElement($name);

        return $this;
    }

    public function closeEle(){
        $this->xmlWriter->endElement();

        return $this;
    }

    public function addAttr(string $name, string $value): XmlUtils{
        $this->xmlWriter->startAttribute( $name );
		$this->xmlWriter->writeRaw($value);
		$this->xmlWriter->endAttribute();

        return $this;
    }

    public function addCont(string $content): XmlUtils{
        $this->xmlWriter->writeRaw($content);

        return $this;
    }

    public function save($saveLocation): void{
        $this->xmlWriter->endDocument();

        copy($this->tmpFileLocation, $saveLocation);

        $this->create();
    }

}
