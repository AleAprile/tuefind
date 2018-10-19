<?php

namespace TueFind\RecordDriver;

class SolrMarc extends SolrDefault
{
    const SCHEME_PREFIX_GND = '(DE-588)';
    const SCHEME_PREFIX_PPN = '(DE-576)';

    /**
     * Search for author and return its id (e.g. GND number or PPN)
     *
     * @param string $author_heading    name of the author and birth/death years if exist, e.g. "Strecker, Christian 1960-"
     * @param string $scheme_prefix     see class constants (SCHEME_PREFIX_*)
     * @return string
     */
    protected function getAuthorIdByHeading($author_heading, $scheme_prefix) {
        $authors = $this->getFieldArrayPCRE('100|700');
        foreach ($authors as $author) {
            $subfield_a = $author->getSubfield('a');
            $subfield_d = $author->getSubfield('d');
            $current_author_heading = $subfield_a->getData();
            if ($subfield_d != false)
                $current_author_heading .= ' ' . $subfield_d;

            if ($author_heading == $subfield_a->getData() || $author_heading == $current_author_heading) {
                $subfields_0 = $author->getSubfieldsArray('0');
                foreach ($subfields_0 as $subfield_0) {
                    if (preg_match('"^' . preg_quote($scheme) . '"', $subfield_0->getData()))
                        return $subfield_0->getData();
                }
                break;
            }
        }
    }

    public function getAuthorGNDNumber($author_heading) {
        return $this->getAuthorIdByHeading($author_heading, self::SCHEME_PREFIX_GND);
    }

    public function getAuthorPPN($author_heading) {
        return $this->getAuthorIdByHeading($author_heading, self::SCHEME_PREFIX_PPN);
    }

    /**
     * Get DOI from 024 instead of doi_str_mv field
     *
     * @return string
     */
    public function getCleanDOI() {
        $results = $this->getMarcRecord()->getFields('024');
        if (!$results)
            return;
        foreach ($results as $result) {
            $subfields = $this->getSubfieldArray($result, ['a', '2'], false);
            if ($subfields && count($subfields) == 2) {
                if (strtolower($subfields[1]) == 'doi');
                    return $subfields[0];
            }
        }
    }


    /**
     * Same as VuFind\RecordDriver\SolrMarc's "getFieldArray",
     * but the first value will be treated as PCRE, allowing multiple fields to be processed.
     *
     * @param string $field_pcre    PCRE of the MARC field numbers to read (without delimiters), e.g. '100|700'
     * @param array  $subfields     The MARC subfield codes to read
     * @param bool   $concat        Should we concatenate subfields?
     * @param string $separator     Separator string (used only when $concat === true)
     *
     * @return array
     */
    protected function getFieldArrayPCRE($field_pcre, $subfields = null, $concat = true,
        $separator = ' '
    ) {
        // Default to subfield a if nothing is specified.
        if (!is_array($subfields)) {
            $subfields = ['a'];
        }

        // Initialize return array
        $matches = [];

        // Try to look up the specified field, return empty array if it doesn't
        // exist.
        $fields = $this->getMarcRecord()->getFields($field_pcre, true);
        if (!is_array($fields)) {
            return $matches;
        }

        // Extract all the requested subfields, if applicable.
        foreach ($fields as $currentField) {
            $next = $this
                ->getSubfieldArray($currentField, $subfields, $concat, $separator);
            $matches = array_merge($matches, $next);
        }

        return $matches;
    }


    /**
     * Wrapper for parent's getFieldArray, allowing multiple fields to be
     * processed at once
     *
     * @param array $fields_and_subfields array(0 => field as string, 1 => subfields as array or string (string only 1))
     * @param bool $concat
     * @param string $separator
     *
     * @return array
     */
    protected function getFieldsArray($fields_and_subfields, $concat=true, $separator=' ') {
        $fields_array = array();
        foreach ($fields_and_subfields as $field_and_subfield) {
            $field = $field_and_subfield[0];
            $subfields = (isset($field_and_subfield[1])) ? $field_and_subfield[1] : null;
            if (!is_null($subfields) && !is_array($subfields)) $subfields = array($subfields);
            $field_array = $this->getFieldArray($field, $subfields, $concat, $separator);
            $fields_array = array_merge($fields_array, $field_array);
        }
        return array_unique($fields_array);
    }

    public function getSuperiorRecord() {
        $_773_field = $this->getMarcRecord()->getField("773");
        if (!$_773_field)
            return NULL;
        $subfields = $this->getSubfieldArray($_773_field, ['w'], /* $concat = */false);
        if (!$subfields)
            return NULL;
        $ppn = substr($subfields[0], 8);
        if (!$ppn || strlen($ppn) != 9)
            return NULL;
        return $this->getRecordDriverByPPN($ppn);
    }

    public function isAvailableInTubingenUniversityLibrary() {
        $ita_fields = $this->getMarcRecord()->getFields("ITA");
        return (count($ita_fields) > 0);
    }

    public function isArticle() {
        $leader = $this->getMarcRecord()->getLeader();

        if ($leader[7] == 'a')
            return true;
        $_935_fields = $this->getMarcRecord()->getFields('935');
        foreach ($_935_fields as $_935_field) {
            $c_subfields = $this->getSubfieldArray($_935_field, ['c']);
            foreach ($c_subfields as $c_subfield) {
                if ($c_subfield == 'sodr')
                    return true;
            }
        }

        return false;
    }

    public function isArticleCollection() {
        $aco_fields = $this->getMarcRecord()->getFields("ACO");
        return (count($aco_fields) > 0);
    }

    public function isPrintedWork() {
        $fields = $this->getMarcRecord()->getFields("007");
        foreach ($fields as $field) {
            if ($field->getData()[0] == 't')
                return true;
        }
        return false;
    }

    public function workIsTADCandidate() {
        return ($this->isArticle() || $this->isArticleCollection()) && $this->isPrintedWork() && $this->isAvailableInTubingenUniversityLibrary();
    }

    public function suppressDisplayByFormat() {
        if (in_array("Weblog", $this->getFormats()))
            return true;

        return false;
    }
}
