<?php

class CRM_Financialtrxnreport_Form_Report_FinancialTransaction extends CRM_Report_Form {

  public function __construct() {
    $monthStart = new DateTime("first day of last month");
    $monthEnd = new DateTime("last day of last month");
    $this->_columns = array(
      'civicrm_financial_account' => array(
        'dao' => 'CRM_Financial_DAO_FinancialAccount',
        'fields' => array(
          'name' => array(
            'title' => ts('Account'),
          ),
          'accounting_code' => array(
            'title' => ts('Accounting Code'),
            'required' => TRUE,
          ),
        ),
      ),
      'civicrm_financial_trxn' => array(
        'dao' => 'CRM_Financial_DAO_FinancialTrxn',
        'fields' => array(
          'total_amount' => array(
            'title' => ts('Amount'),
            'required' => TRUE,
            'dbAlias' => 'SUM(total_amount)',
          ),
          'batch_name' => array( 
            'title' => ts('Batch Name'), 
            'dbAlias' => 'batch_civireport.title',
            'required' => TRUE, 
           ),
          'batch_date' => array(
            'title' => ts('Batch Date'),
            'required' => TRUE,
            'dbAlias' => 'DATE(batch_civireport.title)',
          ),
          'trxn_date' => array(
            'title' => ts('Date'),
          ),
        ),
      ),
      'civicrm_batch' => array(
        'dao' => 'CRM_Batch_DAO_Batch',
        'fields' => array(
        ),
        'filters' => array(
          'name' => array(
            'title' => ts('Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
            'default' => array('from' => $monthStart->format('m/d/Y'), 'to'=> $monthEnd->format('m/d/Y')),
            'dbAlias' => 'DATE(batch_civireport.title)',
          ),
          'status_id' => array(
            'title' => ts('Batch Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_PseudoConstant::get('CRM_Batch_DAO_Batch', 'status_id'),
            'default' => CRM_Core_OptionGroup::getValue('batch_status', 'Exported'),
            'type' => CRM_Utils_Type::T_INT,
          ),
        ),
      ),
    );
    parent::__construct();
  }

  public function preProcess() {
    $this->assign('reportTitle', ts('Monthly Financial Transaction Report by Day'));
    parent::preProcess();
  }

  public function select() {
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

  public function from() {
    $this->_from = "
FROM (
    SELECT cft.id, 0 AS cfi_id, DATE(trxn_date) AS trxn_date, total_amount AS total_amount, to_financial_account_id AS financial_account_id
      FROM civicrm_financial_trxn cft
      WHERE to_financial_account_id IS NOT NULL
UNION
    SELECT cft.id, 0, DATE(trxn_date), -total_amount AS total_amount_2, from_financial_account_id 
      FROM civicrm_financial_trxn cft
      WHERE from_financial_account_id IS NOT NULL
UNION
    SELECT cft.id, cfi.id, DATE(cft.trxn_date) AS trxn_date, -ceft.amount total_amount_3, financial_account_id 
      FROM civicrm_financial_item cfi
        INNER JOIN civicrm_entity_financial_trxn ceft ON ceft.entity_id = cfi.id AND ceft.entity_table = 'civicrm_financial_item'
        INNER JOIN civicrm_financial_trxn cft ON cft.id = ceft.financial_trxn_id AND cft.from_financial_account_id IS NULL
UNION
    SELECT cft.id, cfi.id, DATE(cft.trxn_date) AS trxn_date, ceft.amount total_amount_4, financial_account_id 
      FROM civicrm_financial_item cfi
        INNER JOIN civicrm_entity_financial_trxn ceft ON ceft.entity_id = cfi.id AND ceft.entity_table = 'civicrm_financial_item'
        INNER JOIN civicrm_financial_trxn cft ON cft.id = ceft.financial_trxn_id AND cft.to_financial_account_id IS NULL
  ) AS {$this->_aliases['civicrm_financial_trxn']}

  INNER JOIN civicrm_financial_account {$this->_aliases['civicrm_financial_account']} ON {$this->_aliases['civicrm_financial_trxn']}.financial_account_id = {$this->_aliases['civicrm_financial_account']}.id
  INNER JOIN civicrm_entity_batch ceb ON ceb.entity_id = {$this->_aliases['civicrm_financial_trxn']}.id AND ceb.entity_table = 'civicrm_financial_trxn'
  INNER JOIN civicrm_batch {$this->_aliases['civicrm_batch']} ON {$this->_aliases['civicrm_batch']}.id = ceb.batch_id
";
  }

  public function where() {
    parent::where();
    $this->_having = "HAVING SUM(total_amount) <> 0";
  }

  public function groupBy() {
    $this->_groupBy = " GROUP BY DATE({$this->_aliases['civicrm_batch']}.title), {$this->_aliases['civicrm_financial_account']}.id, {$this->_aliases['civicrm_batch']}.id";
  }

  public function orderBy() {
    $this->_orderBy = " ORDER BY DATE({$this->_aliases['civicrm_batch']}.title), {$this->_aliases['civicrm_financial_account']}.accounting_code, total_amount";
  }

 /**
   * Set limit.
   *
   * @param int $rowCount
   *
   * @return array
   */
  public function limit($rowCount = self::ROW_COUNT_LIMIT) {
    $this->_limit = NULL;
  }

  public function postProcess() {
    parent::postProcess();
  }

  public function alterDisplay(&$rows) {
    if (empty($rows)) {
      return NULL;
    }
    $dateFormat = Civi::settings()->get('dateformatFinancialBatch');
    $onlyNumber = FALSE;
    if ($this->_outputMode == 'csv') {
      $onlyNumber = TRUE;
    }
    foreach ($rows as &$row) {
      $row['civicrm_financial_trxn_total_amount'] = CRM_Utils_Money::format($row['civicrm_financial_trxn_total_amount'], NULL, NULL, $onlyNumber);
      if (!empty($row['civicrm_financial_trxn_trxn_date'])) {
        $row['civicrm_financial_trxn_trxn_date'] = CRM_Utils_Date::customFormat($row['civicrm_financial_trxn_trxn_date'], $dateFormat);
      }
      $row['civicrm_financial_trxn_batch_date'] = CRM_Utils_Date::customFormat($row['civicrm_financial_trxn_batch_date'], $dateFormat);
    }
  }
}
