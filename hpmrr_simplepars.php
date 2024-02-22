<?php

$GLOBALS['db'] = $this->db;
$GLOBALS['log'] = new Log('simplepars_hpmrr');

$hpm_pole = 'model';//$setting["hpm_sku"];
if(!empty($setting["form"][$hpm_pole]))
{
    $hpm_val = $setting["form"][$hpm_pole];
}
else
{
    $hpm_val = false;
}

if($hpm_val && $hpm_pole)
{
    install_hpmrr_table();
    //$res = find_relevant_pr('status', '1');
    $res = find_relevant_pr($hpm_pole, $hpm_val);
    //ищем товары для создание серии
    if(!empty($res))
    {
        $pids = explode(",", $res['pids']);
        $parent_id = $res['parent_id'];
        
        if(count($pids) > 1) //1 товар не образует группу
        {
            if($parent_id) 
            {
                foreach($pids as $product_id)
                {
                    if(!link_exist($parent_id, $product_id))
                    {
                        add_link($parent_id, $product_id);
                    }
                }
            }
            else 
            {
                $parent_id = $pids[0];
                foreach($pids as $product_id)
                {
                    add_link($parent_id, $product_id);
                }
            }
            $GLOBALS['log']->write("add hpmrr link to parent_id ".$parent_id.": ".$res['pids']);
        }
    }
}

function install_hpmrr_table()
{
    $sql = "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "hpmrr_links
    (
        parent_id int(10) unsigned NOT NULL,
        product_id int(10) unsigned NOT NULL,
        sort int(10) unsigned DEFAULT NULL,
        image varchar(255) DEFAULT NULL,
        PRIMARY KEY (parent_id, product_id),
        UNIQUE (product_id,parent_id)
    ) CHARSET=utf8 DEFAULT COLLATE utf8_unicode_ci;";

    $GLOBALS['db']->query($sql);
}
    

function find_relevant_pr($pole, $val)
{
    $sql =  "SELECT GROUP_CONCAT(p.product_id) as pids, MAX(parent_id) as parent_id
    FROM `" . DB_PREFIX . "product` p
    LEFT JOIN `" . DB_PREFIX . "hpmrr_links` hl
    ON p.product_id = hl.product_id
    WHERE p." . $GLOBALS['db']->escape($pole) . " = '" . $GLOBALS['db']->escape($val) . "' 
    GROUP BY p." . $GLOBALS['db']->escape($pole);

    $query = $GLOBALS['db']->query($sql);

    return $query->row;
}

function link_exist($parent_id, $product_id)
{
    $sql =  "SELECT product_id FROM `" . DB_PREFIX . "hpmrr_links` 
    WHERE product_id = '" . (int) $product_id . "' AND
    parent_id = '" . (int) $parent_id . "'";    

    $query = $GLOBALS['db']->query($sql);
    return $query->num_rows;
}

function add_link($parent_id, $product_id)
{
    $sql =  "INSERT INTO `" . DB_PREFIX . "hpmrr_links`(`parent_id`, `product_id`, `sort`, `image`) VALUES ('" . (int) $parent_id . "', '" . (int) $product_id . "', '1', '')";

    $GLOBALS['db']->query($sql);
}