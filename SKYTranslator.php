<?php

$usage = <<<USAGE
SKYTranslator takes master and cloned xml and then stdouts fixed clone,
              with all the elements in the same order, and with all
              the missing CDATA prepended with !FIXME: copied from master

php SKYTranslator.php [master.xml] [cloned.xml] > [stdout|new_clone_fixed.xml]
  master.xml is the master xml
  cloned.xml is the xml which should be diffed against master

USAGE;

$ERR = fopen('php://stderr', 'a');
if (count($argv)<3) {
    die($usage);
}

$masterFile = $argv[1];
$clonedFile = $argv[2];

if (!file_exists($masterFile)) {
    die("ERROR: cannot read $masterFile");
}

if (!file_exists($clonedFile)) {
    die("ERROR: cannot read $clonedFile");
}

main($masterFile, $clonedFile);

function main($masterFile, $clonedFile)
{
    $mXML = new SimpleXMLElement($masterFile, 0, true);
#    $mXML = simplexml_load_string(file_get_contents($masterFile), 'SimpleXMLElement', LIBXML_NOCDATA);
    $cXML = new SimpleXMLElement($clonedFile, 0, true);

    $masterRoot = $mXML->getName();
    $cloneRoot = $cXML->getName();

    /**
     * If root XML tag is different, then exit immediatelly.
     */
    if ($masterRoot !== $cloneRoot) {
        die("XML ROOT error");
    }

    /**
     * True until we find that master and clone
     * are different. We need this to write a nice
     * message to user at the end of parsing.
     */
    $xmlsEqual = true;

    /**
     * Go through all main items and check if they are equal
     * between master and clone xml.
     */
    foreach ($mXML as $entry) {
        debug('normal', '[' . $entry->getName() . ']');

        $isEqual = xmlCompareNode($entry, $cXML);
        $xmlsEqual = $xmlsEqual && $isEqual;
    }

    if ($xmlsEqual) {
        echo "\n\n ******* XML elements ARE equal! ********\n\n";
    } else {
        echo "\n\n !!!!!!! XML elements are NOT equal !!!!!!!!!";

        $newXMLFile = saveXML($cXML);
        echo "\n\n         See: $newXMLFile\n\n";
    }
}

/**
 * Saves a pretty print version of XML.
 */
function saveXML($XML)
{
    $newXMLFile = "new_clone_" . time() . ".xml";

    $domxml = new DOMDocument('1.0');
    $domxml->encoding = 'utf-8';
    $domxml->preserveWhiteSpace = false;
    $domxml->formatOutput = true;
    $domxml->loadXML(
        str_replace(array('&lt;','&gt;'),array('<','>')
        ,$XML->asXML())
    );
    $domxml->save($newXMLFile,LIBXML_NOEMPTYTAG);

    return $newXMLFile;
}

/**
 * Returns true if given two attribute arrays are equal.
 * If all items in $a are present in $b.
 */
function areAttributesEqual($a, $b)
{
    if (!$b) {
        return false;
    }

    foreach ($a as $aKey => $aValue) {
        if (!array_key_exists($aKey, $b)) {
            return false;
        }

        if ($b[$aKey] !== $aValue) {
            return false;
        }
    }

    return true;
}

/**
 * Because $node->attributes() returns SimpleXMLObject
 * and we need array.
 */
function getAttributes($node)
{
    $attributes = [];

    foreach ($node->attributes() as $key => $value) {
        $attributes[$key] = (string)$value;
    };

    return $attributes;
}

/**
 * Finds XML node in clone XML that has given tag
 * and attributes. Returns null if this kind of
 * node does not exist.
 */
function findInClone($cXML, $tag, $attributes)
{
    /**
     * We only need to check root elements. Child
     * elements will be checked recursivelly in later
     * steps.
     */
    foreach ($cXML as $entry) {
        $cTag = $entry->getName();
        $cAttributes = getAttributes($entry);

        if ($tag !== $cTag) {
            // Not the right tag, this one has different name.
            // Keep searching.
            continue;
        }

        if ($attributes && !areAttributesEqual($attributes, $cAttributes)) {
            // Not the right tag, this one has different attribute
            // values. Keep searching.
            continue;
        }

        /**
         * Nice, we have found the same tag, let's return it
         */
        return $entry;
    }

    return null;
}

/**
 * This function removes node content and writes
 * FIXME into it. If node has child elements, FIXME
 * gets written to all child elements also.
 */
function markAsFixMe(&$mXMLNode)
{
    $txt = (string)$mXMLNode;

    if (!$mXMLNode->count()) {
        (strlen($mXMLNode[0])) ? $mXMLNode[0] = "<![CDATA[!FIXME:".$mXMLNode[0]."]]>" : $mXMLNode[0] = "";
        return;
    }

    foreach ($mXMLNode as $entry) {
        markAsFixMe($entry);
    }
}

/**
 * Appends one XML part into another
 */
function xmlAppend(SimpleXMLElement $to, SimpleXMLElement $from) {
    $toDom = dom_import_simplexml($to);
    $fromDom = dom_import_simplexml($from);
    $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
}

function xmlCompareNode($mXMLNode, $cXML, $depth = 1)
{
    /**
     * Tag and attributes from master xml
     */
    $mTag = $mXMLNode->getName();
    $mAttributes = getAttributes($mXMLNode);

    /**
     * We try to find master's tag with the same attributes in clone xml
     */
    $cXMLNode = findInClone($cXML, $mTag, $mAttributes);
    $found = $cXMLNode !== null;

    if ($found) {
        debug('normal', "FOUND: $mTag " . json_encode($mAttributes), $depth);
    } else {
        debug('warning', "MISSING: $mTag " . json_encode($mAttributes), $depth);

        /**
         * This mXMLNode is missing in clone XML. We'll first
         * mark it as FIXME and then we'll append it to
         * clone XML.
         */
        markAsFixMe($mXMLNode);

        xmlAppend($cXML, $mXMLNode);

        /**
         * If tag is missing in clone, no need to check for any of child nodes.
         */
        return false;
    }

    /**
     * True if all child nodes in master are also in clone
     */
    $isNodeEqual = true;

    /**
     * Check all child nodes
     */
    foreach ($mXMLNode as $entry) {
        $isEqual = xmlCompareNode($entry, $cXMLNode, $depth + 1);
        $isNodeEqual = $isNodeEqual && $isEqual;
    }

    return $isNodeEqual;
}

/**
 * Simple debug
 */
function debug($type, $txt, $depth = 0)
{
    echo ($type === 'warning' ? '!!' : '  ') . str_repeat(' ', $depth * 4) . $txt . "\n";
}
