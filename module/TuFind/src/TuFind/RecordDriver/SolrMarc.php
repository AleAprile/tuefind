<?php

namespace TuFind\RecordDriver;
use VuFind\Exception\LoginRequired as LoginRequiredException;

class SolrMarc extends \TuFind\RecordDriver\SolrDefault
{
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
}
