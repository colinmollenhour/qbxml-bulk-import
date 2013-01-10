<?php

class QbSimplexmlElement extends SimpleXMLElement
{

    /**
     * @param $path
     * @param $length
     * @param bool|string $nested
     * @return string|null
     */
    public function truncate($path, $length, $nested=false)
    {
        $arr = is_array($path) ? $path : explode('/', $path);
        $last = sizeof($arr)-1;
        foreach ($arr as $i=>$nodeName) {
            if ($last===$i) {
                if (isset($this->$nodeName)) {
                    $value = (string)$this->$nodeName;
                    if (strlen($value) > $length) {
                        if ($nested) {
                            $parts = explode($nested,$value);
                            foreach($parts as $j => $part) {
                                $parts[$j] = substr($part,0,$length);
                            }
                            $this-$nodeName = implode($nested,$parts);
                        }
                        else {
                            $this->$nodeName = substr($value,0,$length);
                        }
                    }
                    return (string)$this->$nodeName;
                }
            } else {
                if ($this->hasChildren()) {
                    $childCount = 0;
                    foreach ($this->children() as $_nodeName => $child) {
                        if ($_nodeName == $nodeName) {
                            $childCount++;
                            $retVal = $child->truncate(array_slice($arr,$i+1), $length);
                        }
                    }
                    if ($childCount == 1) {
                        return $retVal;
                    }
                }
            }
        }
        return null;
    }

    /**
     * @return boolean
     */
    public function hasChildren()
    {
        if (!$this->children()) {
            return false;
        }
        foreach ($this->children() as $k=>$child) {
            return true;
        }
        return false;
    }

    /**
     * @param $name
     * @return string
     */
    public function getAttribute($name)
    {
        $attrs = $this->attributes();
        return isset($attrs[$name]) ? (string)$attrs[$name] : null;
    }

    /**
     * @param string $filename
     * @param int|boolean $level if false
     * @return string
     */
    public function asNiceXml($filename='', $level=0)
    {
        if (is_numeric($level)) {
            $pad = str_pad('', $level*3, ' ', STR_PAD_LEFT);
            $nl = "\n";
        } else {
            $pad = '';
            $nl = '';
        }

        $out = $pad.'<'.$this->getName();

        if ($attributes = $this->attributes()) {
            foreach ($attributes as $key=>$value) {
                $out .= ' '.$key.'="'.str_replace('"', '\"', (string)$value).'"';
            }
        }

        if ($this->hasChildren()) {
            $out .= '>'.$nl;
            foreach ($this->children() as $child) {
                $out .= $child->asNiceXml('', is_numeric($level) ? $level+1 : true);
            }
            $out .= $pad.'</'.$this->getName().'>'.$nl;
        } else {
            $value = (string)$this;
            if (strlen($value)) {
                $out .= '>'.$this->xmlentities($value).'</'.$this->getName().'>'.$nl;
            } else {
                $out .= '/>'.$nl;
            }
        }

        if ((0===$level || false===$level) && !empty($filename)) {
            file_put_contents($filename, $out);
        }

        return $out;
    }

    /**
     * @param  string
     * @return string
     */
    public function xmlentities($value = null)
    {
        if (is_null($value)) {
            $value = $this;
        }
        $value = (string)$value;
        $value = str_replace(array('&', '"', "'", '<', '>'), array('&amp;', '&quot;', '&apos;', '&lt;', '&gt;'), $value);
        return $value;
    }

    /**
     * @param QbSimplexmlElement $source
     * @return QbSimplexmlElement
     */
    public function appendChild($source)
    {
        if ($source->children()) {
            $name = $source->getName();
            $child = $this->addChild($name);
        } else {
            $child = $this->addChild($source->getName(), $this->xmlentities($source));
        }
        foreach ($source->attributes() as $key=>$value) {
            $child->addAttribute($key, $this->xmlentities($value));
        }
        foreach ($source->children() as $sourceChild) {
            $child->appendChild($sourceChild);
        }
        return $this;
    }

    /**
     * @param QbSimplexmlElement $source
     * @return QbSimplexmlElement
     */
    public function populate($source)
    {
        if (!$source instanceof QbSimplexmlElement) {
            return $this;
        }
        foreach ($source->children() as $child) {
            $this->appendChild($child);
        }

        return $this;
    }

}
