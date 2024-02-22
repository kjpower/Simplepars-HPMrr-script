<?php

//скрипт в режиме теста и подбирает все включенные товары в качестве серии
$GLOBALS['db'] = $this->db;
$GLOBALS['log'] = new Log('simplepars_hpmrr');

$test_mode = true;
install_hpmrr_table();
$pr_id = $script_data['permit']['add']['pr_id'];
if(empty($pr_id))
{ 
    $pr_id = $script_data['permit']['up']['pr_id']; 
}

if($test_mode)
{
    $hpm_pole = 'status';
    $hpm_status = '1';
    $hpm_val = '1';
}
else
{
    $hpm_pole = $setting["hpm_sku"];
    $hpm_status = $setting["r_hpm"];
    if(!empty($setting["form"][$hpm_pole]))
    {
        $hpm_val = $setting["form"][$hpm_pole];
    }
    else
    {
        $hpm_val = false;
    }
}

function find_relevant_pr($pole, $val)
{
    $sql =  "SELECT GROUP_CONCAT(p.product_id) as pids, MAX(parent_id) as parent_id
    FROM `" . DB_PREFIX . "product` p
    LEFT JOIN `" . DB_PREFIX . "hpmrr_links` hl
    ON p.product_id = hl.product_id
    WHERE p." . $GLOBALS['db']->escape($pole) . " = '" . $GLOBALS['db']->escape($val) . "' 
    GROUP BY p." . $GLOBALS['db']->escape($pole);

    $res = [];

    $query = $this->db->query($sql);

    return $query->row;
}