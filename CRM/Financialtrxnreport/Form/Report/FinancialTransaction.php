<?php

class CRM_Financialtrxnreport_Form_Report_FinancialTransaction extends CRM_Report_Form {

  function __construct() {
    $this->_columns = array(
      'civicrm_financial_trxn' => array(
        'dao' => 'CRM_Financial_DAO_FinancialTrxn',
        'fields' => array(
          'trxn_date' => array(
            'title' => ts('Date'),
            'required' => TRUE,
          ),
          'total_amount' => array(
            'title' => ts('Amount'),
            'required' => TRUE,
            'dbAlias' => 'IF(ISNULL(SUM(total_amount_1)), 0, SUM(total_amount_1)) - IF(ISNULL(SUM(total_amount_2)), 0 , SUM(total_amount_2)) - IF(ISNULL(SUM(total_amount_3)), 0, SUM(total_amount_3))',
          ),
        ),
        'filters' => array(
          'trxn_date' => array(
            'title' => ts('Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
        ),
      ),
      'civicrm_financial_account' => array(
        'dao' => 'CRM_Financial_DAO_FinancialAccount',
        'fields' => array(
          'accounting_code' => array(
            'title' => ts('Accounting Code'),
            'required' => TRUE,
          ),
        ),
      ),
      'civicrm_financial_item' => array(
        'dao' => 'CRM_Financial_DAO_FinancialItem',
        'fields' => array(),
      ),
    );
    parent::__construct();
  }

  function preProcess() {
    $this->assign('reportTitle', ts('Monthly Financial Transaction Report by Day'));
    parent::preProcess();
  }
  
  function setDefaultValues($freeze = true) {
    /* $defaults['trxn_date_from']= '07/19/2016'; */
    /* $defaults['trxn_date_relative'] = 0; */
    return $defaults;
  }

  function select() {
    $select = $this->_columnHeaders = array();

    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) ||
            CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {
            $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
          }
        }
      }
    }

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  function from() {
    $exportedBatchStatus = CRM_Core_OptionGroup::getValue('batch_status', 'Exported', 'name');
    $this->_from = "
 FROM civicrm_financial_account {$this->_aliases['civicrm_financial_account']}

LEFT JOIN
( 
  SELECT trxn_date, total_amount total_amount_1, to_financial_account_id FROM civicrm_financial_trxn cft
  INNER JOIN civicrm_entity_batch ceb ON ceb.entity_id = cft.id AND ceb.entity_table = 'civicrm_financial_trxn'
  INNER JOIN civicrm_batch cb ON cb.id = ceb.batch_id AND cb.status_id = {$exportedBatchStatus}
) {$this->_aliases['civicrm_financial_trxn']} ON {$this->_aliases['civicrm_financial_account']}.id = {$this->_aliases['civicrm_financial_trxn']}.to_financial_account_id

LEFT JOIN 
(
  SELECT total_amount total_amount_2, from_financial_account_id FROM civicrm_financial_trxn cft
  INNER JOIN civicrm_entity_batch ceb ON ceb.entity_id = cft.id AND ceb.entity_table = 'civicrm_financial_trxn'
  INNER JOIN civicrm_batch cb ON cb.id = ceb.batch_id AND cb.status_id = {$exportedBatchStatus}
) {$this->_aliases['civicrm_financial_trxn']}_1 ON {$this->_aliases['civicrm_financial_account']}.id = {$this->_aliases['civicrm_financial_trxn']}_1.from_financial_account_id

LEFT JOIN 
(
  SELECT cfi.amount total_amount_3, financial_account_id FROM civicrm_financial_item cfi
  INNER JOIN civicrm_entity_financial_trxn ceft ON ceft.entity_id = cfi.id AND ceft.entity_table = 'civicrm_financial_item'
  INNER JOIN civicrm_entity_batch ceb ON ceb.entity_id = ceft.financial_trxn_id AND ceb.entity_table = 'civicrm_financial_trxn'
  INNER JOIN civicrm_batch cb ON cb.id = ceb.batch_id AND cb.status_id = {$exportedBatchStatus}
) {$this->_aliases['civicrm_financial_item']} ON {$this->_aliases['civicrm_financial_account']}.id = {$this->_aliases['civicrm_financial_item']}.financial_account_id

";

  }

  function where() {
    $clauses = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          $clause = NULL;
          if (CRM_Utils_Array::value('operatorType', $field) & CRM_Utils_Type::T_DATE) {
            $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
            $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
            $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

            $clause = $this->dateClause($field['name'], $relative, $from, $to, $field['type']);
          }
          else {
            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
            }
          }

          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }
    if (empty($clauses)) {
      $this->_where = "WHERE ( 1 ) ";
    }
    else {
      $this->_where = "WHERE " . implode(' AND ', $clauses);
    }
  }

  function groupBy() {
    $this->_groupBy = " GROUP BY {$this->_aliases['civicrm_financial_trxn']}.trxn_date, {$this->_aliases['civicrm_financial_account']}.id";
  }

  function orderBy() {
    $this->_orderBy = " ORDER BY {$this->_aliases['civicrm_financial_account']}.accounting_code";
  }

  function postProcess() {

    $this->beginPostProcess();

    $sql = $this->buildQuery(FALSE);

    $rows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function alterDisplay(&$rows) {
    
  }
}
