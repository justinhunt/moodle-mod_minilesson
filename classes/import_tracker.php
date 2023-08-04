<?php


namespace mod_minilesson;


class import_tracker
{
    /** @var array */
    protected $_row;

    /**
     * The columns shown on the table.
     * @var array
     */
    public $columns = [];
    /** @var array column headers */
    protected $headers = [];

    /**
     * uu_progress_tracker constructor.
     */
    public function __construct($keycolumns) {

        $base_headers = ['id'=>'ID','line'=>'Line','status'=>'Status'];
        $headers = array_merge($base_headers,$keycolumns );
        $this->columns= array_keys($headers);
        $this->headers = array_keys($headers);
    }

    /**
     * Print table header.
     * @return void
     */
    public function start() {
        $ci = 0;
        echo '<table id="iiresults" class="generaltable boxaligncenter flexible-wrap" summary="'.get_string('importitemsresult', constants::M_COMPONENT).'">';
        echo '<tr class="heading r0">';
        foreach ($this->headers as $key => $header) {
            echo '<th class="header c'.$ci++.'" scope="col">'.$header.'</th>';
        }
        echo '</tr>';
        $this->_row = null;
    }

    /**
     * Flush previous line and start a new one.
     * @return void
     */
    public function flush() {
        if (empty($this->_row) or empty($this->_row['line']['normal'])) {
            // Nothing to print - each line has to have at least number
            $this->_row = array();
            foreach ($this->columns as $col) {
                $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
            }
            return;
        }
        $ci = 0;
        $ri = 1;
        echo '<tr class="r'.$ri.'">';
        foreach ($this->_row as $key=>$field) {
            foreach ($field as $type=>$content) {
                if ($field[$type] !== '') {
                    $field[$type] = '<span class="ii'.$type.'">'.$field[$type].'</span>';
                } else {
                    unset($field[$type]);
                }
            }
            echo '<td class="cell c'.$ci++.'">';
            if (!empty($field)) {
                echo implode('<br />', $field);
            } else {
                echo '&nbsp;';
            }
            echo '</td>';
        }
        echo '</tr>';
        foreach ($this->columns as $col) {
            $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
        }
    }

    /**
     * Add tracking info
     * @param string $col name of column
     * @param string $msg message
     * @param string $level 'normal', 'warning' or 'error'
     * @param bool $merge true means add as new line, false means override all previous text of the same type
     * @return void
     */
    public function track($col, $msg, $level = 'normal', $merge = true) {
        if (empty($this->_row)) {
            $this->flush(); //init arrays
        }
        if (!in_array($col, $this->columns)) {
            debugging('Incorrect column:'.$col);
            return;
        }
        if ($merge) {
            if ($this->_row[$col][$level] != '') {
                $this->_row[$col][$level] .='<br />';
            }
            $this->_row[$col][$level] .= $msg;
        } else {
            $this->_row[$col][$level] = $msg;
        }
    }

    /**
     * Print the table end
     * @return void
     */
    public function close() {
        $this->flush();
        echo '</table>';
    }
}//end of class
