<?php
/**
*   Updates docbook class files by adding missing methods and
*   constructors
*
*   @author Anant Narayanan <anant@kix.in>
*   @author Christian Weiske <cweiske@php.net>
*/
class updateMethods
{
    /* Stores class under consideration and missing classes */
    public $methodCount=0;
    public $missingClasses = array();

    function __construct()
    {
        //remove own filename
        array_shift($GLOBALS['argv']);

        $files = $GLOBALS['argv'];
        foreach ($files as $file) {
            if (substr($file, -4) != '.xml') {
                echo '"' . $file . "\" is no xml file\n";
                continue;
            }
            if (!file_exists($file)) {
                echo 'File "' . $file . "\" does not exist\n";
                continue;
            }

            $classname = substr(basename($file), 0, -4);
            $this->updateClass($classname, $file);
        }
    }

    function updateClass($classname, $file)
    {
        /* Obtain reflection object for current class */
        $refObject = new ReflectionClass($classname);
        $classname = $refObject->getName();
        echo 'Checking ' . str_pad($classname, 20, ' ');
        $childMethods = $refObject->getMethods();
        $parent = $refObject->getParentClass();
        if ($parent === false) {
            $parent = null;
        }
        if ($parent !== null) {
            $parentMethods = $parent->getMethods();
            $trueMethods = $this->cleanMethods($childMethods, $parentMethods);
        } else {
            $trueMethods = $childMethods;
        }
        echo ' ' . count($trueMethods) . " methods\n";
        $xml = new DOMDocument();
        $xml->load($file);
        $xpath = new DOMXPath($xml);
        foreach ($trueMethods as $key => $methodObj) {
            /* Update each method */
            $this->updateMethod($classname, $file, $key, $methodObj, $xml, $xpath);
        }
    }

    function updateMethod($classname, $file, $sNo, $method, $doc, $xpath)
    {
        $methodName = $method->getName();
        $compelArgs = $method->getNumberOfRequiredParameters();
        $totalArgs = $method->getNumberOfParameters();

        preg_match_all('/^([A-Za-z]*[a-z])[A-Z]/', $classname, $matches);
        if (isset($matches[1][0])) {
            $prefix = strtolower($matches[1][0]);
        } else {
            $prefix = strtolower(substr($classname, 0, 3));
            echo 'Could not determine prefix for class "' . $classname . '". Using "' . $prefix . "\"\n";
        }

        $daClass = strtolower($classname);
        $daMethod = strtolower($methodName);

        if ($methodName == "__construct") {
            //constructor
            $ismethod = false;
            $daID = $prefix . '.' . $daClass . '.constructor';
        } else if (substr($methodName, 0, 3) == "new") {
            //constructor
            $ismethod = false;
            $daID = $prefix . '.' . $daClass . '.constructor.' . $daMethod;
        } else if (substr($methodName, 0, 2) == '__') {
            return;
        } else {
            //normal method
            $ismethod = true;
            $daID = $prefix . '.' . $daClass . '.method.' . $daMethod;
        }


        if ($ismethod) {
            $path = '/classentry/methods/method[@id="' . $daID . '"]/funcsynopsis/funcprototype';
        } else {
            $path = '/classentry/constructors/constructor[@id="' . $daID . '"]/funcsynopsis/funcprototype';
        }
        $functionNodes =
            $xpath->query($path);


        if ($functionNodes->length == 0) {
            /* Method not present, Add it */
            if ($ismethod) {
                $xmlMethod = $doc->createElement('method', "\n   ");
            } else {
                $xmlMethod = $doc->createElement('constructor', "\n   ");
            }
            $xmlMethod->setAttribute('id', $daID);

            $xmlSynopsis = $doc->createElement('funcsynopsis', "\n    ");
            $xmlPrototype = $doc->createElement('funcprototype', "\n     ");
            $xmlFuncdef = $doc->createElement('funcdef', " ");
            if ($ismethod || $methodName == "__construct") {
                $xmlFunction = $doc->createElement('function', $daMethod);
            } else {
                //alternative constructors need Classname::new_* as funcname
                $xmlFunction = $doc->createElement('function', $classname . '::' . $daMethod);
            }

            $xmlFuncdef->appendChild($xmlFunction);
            $xmlParamDefs = array();

            if($totalArgs > 0) {
                /* Function has arguments */
                foreach($method->getParameters() as $param) {
                    $xmlParamdef = $doc->createElement('paramdef');
                    if($param->getClass()) {
                        /* Parameter is of object type */
                        $xmlClassname = $doc->createElement('classname', $param->getClass()->getName());
                        $xmlParamdef->appendChild($xmlClassname);
                    }
                    $xmlParameter = $doc->createElement('parameter');
                    if($param->isOptional()) {
                        /* Parameter is optional */
                        if($param->isDefaultValueAvailable()) {
                            /* Parameter has default value */
                            $xmlOptional =
                                $doc->createElement('optional', $param->getName()."=".$param->getDefaultValue());
                        }
                        else {
                            $xmlOptional =
                                $doc->createElement('optional', $param->getName());
                        }
                        $xmlParameter->appendChild($xmlOptional);
                    }
                    else {
                        $xmlParameter->nodeValue = $param->getName();
                    }
                    $xmlParamdef->appendChild($xmlParameter);
                    $xmlParamDefs[] = $xmlParamdef;
                }
            }
            else {
                /* Function has NO arguments */
                $xmlParamdef = $doc->createElement('paramdef', "void");
            }

            /* Filler nodes to maintain indentation :) */
            $indentFuncdef = $doc->createTextNode("\n     ");
            $indentParamdef = $doc->createTextNode("\n    ");
            $indentPrototype = $doc->createTextNode("\n   ");
            $indentSynopsis = $doc->createTextNode("\n   ");
            $indentShortDesc = $doc->createTextNode("\n   ");
            $indentDesc = $doc->createTextNode("\n  ");
            $indentMethod = $doc->createTextNode("\n\n  ");

            /* Appending child nodes in order */
            $xmlPrototype->appendChild($xmlFuncdef);
            $xmlPrototype->appendChild($indentFuncdef);
            foreach ($xmlParamDefs as $def) {
                $xmlPrototype->appendChild($xmlParamdef);
            }
            $xmlPrototype->appendChild($indentParamdef);
            $xmlSynopsis->appendChild($xmlPrototype);
            $xmlSynopsis->appendChild($indentPrototype);

            /* Add nodes for shortdesc and desc */
            $xmlShortDesc = $doc->createElement('shortdesc', "\n\n   ");
            $xmlDesc = $doc->createElement('desc', "\n\n   ");
            $xmlMethod->appendChild($xmlSynopsis);
            $xmlMethod->appendChild($indentSynopsis);
            $xmlMethod->appendChild($xmlShortDesc);
            $xmlMethod->appendChild($indentShortDesc);
            $xmlMethod->appendChild($xmlDesc);
            $xmlMethod->appendChild($indentDesc);

            /* Save the xml file after adding the whole method node */
            if($ismethod) {
                echo "M";
                $topLevel = $doc->getElementsByTagName('methods');
            } else {
                echo "C";
                $topLevel = $doc->getElementsByTagName('constructors');
            }
            $topLevel = $topLevel->item(0);
            echo "Updating ".$daID."\n";
            $topLevel->appendChild($xmlMethod);
            $topLevel->appendChild($indentMethod);
            $doc->save($file);

            $this->methodCount += 1;
        }
        else {
            /* Method exists
            * Add code to check validity later
            */
        }
    }//function updateMethod($classname, file, $sNo, $method, $doc, $xpath)



    /* Return methods belonging ONLY to the child */
    function cleanMethods($cMethods, $pMethods)
    {
        $result = array();
        foreach($cMethods as $cMethod) {
            $flag = 1;
            foreach($pMethods as $pMethod) {
                if($cMethod->getName() == $pMethod->getName()) {
                    $flag = 0;
                }
            }
            if($flag) {
                $result[] = $cMethod;
            }
        }
        return $result;
    }
}

$doIt = new updateMethods();

if($doIt->methodCount==0) {
    echo "\n\nNo Methods to Update! Quitting...\n\n";
}
else {
    echo "\n\n".$doIt->methodCount." methods were updated!";
    echo "\nThe following classes' xml source files do not exist and therefore were NOT updated:\n";
    foreach($doIt->missingClasses as $missClass) {
        echo $missClass."\n";
    }
}

echo "\n\n";

?>
