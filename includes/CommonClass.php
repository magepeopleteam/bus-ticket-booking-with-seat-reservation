<?php
if (!defined('ABSPATH')) exit;  // if direct access

class CommonClass
{
    public function __construct()
    {

    }


    function convert_datepicker_dateformat()
    {
        $date_format = get_option('date_format');
        // return $date_format;
        // $php_d     = array('F', 'j', 'Y', 'm','d','D','M','y');
        // $js_d   = array('d', 'M', 'yy','mm','dd','tt','mm','yy');
        $dformat = str_replace('d', 'dd', $date_format);
        $dformat = str_replace('m', 'mm', $dformat);
        $dformat = str_replace('Y', 'yy', $dformat);

        if ($date_format == 'Y-m-d' || $date_format == 'm/d/Y' || $date_format == 'd/m/Y' || $date_format == 'Y/d/m' || $date_format == 'Y-d-m') {
            return str_replace('/', '-', $dformat);
        } elseif ($date_format == 'Y.m.d' || $date_format == 'm.d.Y' || $date_format == 'd.m.Y' || $date_format == 'Y.d.m' || $date_format == 'Y.d.m') {
            return str_replace('.', '-', $dformat);
        } else {
            return 'yy-mm-dd';
        }
    }



}

