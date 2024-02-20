<?php

$test_mode = true;
$GLOBALS['db'] = $this->db;
$GLOBALS['log'] = new Log('simplepars_hpmrr');
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

if($hpm_val && $hpm_pole && $hpm_status && check_exist_hpmrr_table())
{
    $serie = get_serie($pr_id);
    //echo "<PRE>";var_dump($serie);echo "</PRE>";
    if($serie)
    {
        chain_for_product_in_serie($pr_id, $serie, $hpm_pole, $hpm_val);
    }
    else
    {
        chain_for_new_product($pr_id, $hpm_pole, $hpm_val);
    }
}

function check_exist_hpmrr_table()
{
    $sql = "SHOW TABLES LIKE '" . DB_PREFIX . "hpmrr_links'";
    $res = $GLOBALS['db']->query($sql);
    
    return $res->num_rows;
}

function chain_for_product_in_serie($pr_id, $serie, $hpm_pole, $hpm_val)
{
    $rel_prds = find_relevant_pr($hpm_pole, $hpm_val);
    $parent_id = $serie['parent_id'];
    $is_parent = $pr_id == $parent_id;
    
    if($is_parent)
    {
        //product is parent
        $child_ids_for_remove = [];
        foreach($serie['childs'] as $child_id)
        {
            $child_is_relevant = isset($rel_prds[$child_id]);
            if(!$child_is_relevant)
            {
                $child_ids_for_remove[] = $child_id;
            }
        }

        if($child_ids_for_remove)
        {
            $remove_series = count($child_ids_for_remove) == count($serie['childs']);
            if($remove_series)
            {
                remove_serie_by_parent($parent_id);
                chain_for_new_product($pr_id, $hpm_pole, $hpm_val);
            }
            else
            {
                remove_link($parent_id, $child_ids_for_remove);
            }
        }
    }
    else
    {
        //product is child
        $parent_is_relevant = isset($rel_prds[$serie['parent_id']]);

        if(!$parent_is_relevant)
        {
            remove_link($parent_id, $pr_id);
            chain_for_new_product($pr_id, $hpm_pole, $hpm_val);
        }

    }

}
function chain_for_new_product($pr_id, $hpm_pole, $hpm_val)
{
    $rel_prds = find_relevant_pr($hpm_pole, $hpm_val);

    if($rel_prds)
    {
        $relevant_parent_id = find_first_parent($rel_prds);
        if($relevant_parent_id)
        {
            add_link($relevant_parent_id, $pr_id);
        }
        else
        {
            $free_prds = get_free_pr($rel_prds);
            create_serie($free_prds);
        }
    }
}

function create_serie($prds)
{
    if(is_array($prds) && !empty($prds))
    {
        $min_price = PHP_INT_MAX;
        $parent_id = -1;

        foreach($prds as $pid => $pinfo)
        {
            if($pinfo['status'] && $pinfo['price'] < $min_price)
            {
                $parent_id = $pid;
                $min_price = $pinfo['price'];
            }
        }

        if($parent_id != -1)
        {
            add_link($parent_id, array_keys($prds));
        }
    }
}

function hard_remove_serie_by_pid($pid)
{
    $GLOBALS['log']->write('remove HPMrr serie by parent id :' . $parent_id);
    $sql =  "DELETE FROM `" . DB_PREFIX . "hpmrr_links` 
    WHERE parent_id = '" . (int) $pid . "' OR 
    product_id = '" . (int) $pid . "'";
    
    //echo $sql."</br>";
    $GLOBALS['db']->query($sql);
}

function remove_serie_by_parent($parent_id)
{

    $GLOBALS['log']->write('remove HPMrr serie by parent id :' . $parent_id);
    $sql =  "DELETE FROM `" . DB_PREFIX . "hpmrr_links` 
    WHERE parent_id = '" . (int) $parent_id . "'";
    
    //echo $sql."</br>";
    $GLOBALS['db']->query($sql);
}

function remove_link($parent_id, $pid)
{
    
    $format = "(parent_id = '%d' AND product_id = '%d')";

    if(is_array($pid))
    {
        $vals = [];
        foreach($pid as $id)
        {
            $vals[] = sprintf($format, $parent_id, $id);
        }
        $GLOBALS['log']->write('remove link ' . $parent_id . " : " . implode(",", $pid));
        $sql =  "DELETE FROM `" . DB_PREFIX . "hpmrr_links` WHERE " . implode(" OR ", $vals);    
    }
    else
    {
        $GLOBALS['log']->write('remove link ' . $parent_id . " : " . $pid);
        $sql =  "DELETE FROM `" . DB_PREFIX . "hpmrr_links` WHERE " . sprintf($format, $parent_id, $pid);
    }
    
    //echo $sql."</br>";
    $GLOBALS['db']->query($sql);
}

function add_link($parent_id, $pid)
{
    $format = "('%d','%d')";

    if(is_array($pid))
    {
        
        $vals = [];
        foreach($pid as $id)
        {
            $vals[] = sprintf($format, $parent_id, $id);
        }
        $GLOBALS['log']->write('add links ' . $parent_id . " : " . implode(",", $pid));
        $sql =  "INSERT INTO `" . DB_PREFIX . "hpmrr_links`(`parent_id`, `product_id`) VALUES " . implode(",", $vals);    
    }
    else
    {
        $GLOBALS['log']->write('add link ' . $parent_id . " : " . $pid);
        $sql =  "INSERT INTO `" . DB_PREFIX . "hpmrr_links`(`parent_id`, `product_id`) VALUES " . sprintf($format, $parent_id, $pid);    
    }
    
    //echo $sql."</br>";
    $GLOBALS['db']->query($sql);
}

function get_serie($pid)
{
    $sql =  "SELECT * FROM `" . DB_PREFIX . "hpmrr_links` 
    WHERE (SELECT parent_id FROM `" . DB_PREFIX . "hpmrr_links` WHERE product_id = '" . (int) $pid . "' LIMIT 1)";

    $query = $GLOBALS['db']->query($sql);

    if($query->num_rows)
    {
        $res = [];
        $res['parent_id'] = $query->row['parent_id'];
        $res['childs'] = [];

        foreach($query->rows as $row)
        {
            if($row['product_id'] != $row['parent_id'])
            {
                $res['childs'][] = $row['product_id'];
            }
        }

        return $res;
    }
    else
    {
        return false;
    }
}

function find_relevant_pr($pole, $val)
{
    $sql =  "SELECT p.product_id, parent_id, status, price FROM `" . DB_PREFIX . "product` p
    LEFT JOIN `" . DB_PREFIX . "hpmrr_links` hl
    ON p.product_id = hl.product_id
    WHERE p.$pole = '" . $GLOBALS['db']->escape($val) . "' ";

    $query = $GLOBALS['db']->query($sql);

    $res = [];

    if($query->num_rows)
    {
        foreach($query->rows as $row)
        {
            $res[$row['product_id']] = [
                'parent_id' => $row['parent_id'],
                'status'    => $row['status'],
                'price'     => $row['price']
            ];
        }
    }

    return $res;
}

function get_free_pr($pids)
{
    if(is_array($pids) && !empty($pids))
    {
        foreach($pids as $pid => $pinfo)
        {
            //remove all pr with parent
            if($pinfo['parent_id'])
            {
                unset($pids[$pid]);
            }
        }
    }

    return $pids;
}

function find_first_parent($pids)
{
    if(is_array($pids) && !empty($pids))
    {
        foreach($pids as $pid => $pinfo)
        {
            if($pid == $pinfo['parent_id'])
            {
                return $pid;
            }
        }
    }
    return false;
}