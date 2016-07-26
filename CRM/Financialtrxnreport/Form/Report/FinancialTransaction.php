<?php

class CRM_Financialtrxnreport_Form_Report_FinancialTransaction extends CRM_Report_Form {

  function __construct() {
    $this->_columns = array();
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  function preProcess() {
    $this->assign('reportTitle', ts('Monthly Financial Transaction Report by Day'));
    parent::preProcess();
  }

  function select() {}

  function from() {}

  function where() {}

  function groupBy() {}

  function orderBy() {}

  function postProcess() {

    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact']);
    $sql = $this->buildQuery(TRUE);

    $rows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function alterDisplay(&$rows) {}
}
