<?php
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_nga.php');

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();

// Read in passed-in parameters
$name = utf8_normalize_nfc(request_var('name', '', true));
$view = utf8_normalize_nfc(request_var('view', 'full', true));
$set =  utf8_normalize_nfc(request_var('set', '', true));


$name = $db->sql_escape(html_entity_decode($name, ENT_QUOTES));

// look for the card requested
$sql = 'SELECT c.name, c.mana_cost, c.cmc, c.colors, c.types, c.rules_text, c.flavor_text, c.power, c.toughness, c.loyalty, c.is_banned, p.render_url, p.rarity, s.name as set_name, s.short_name
        FROM nga_cards AS c ';
//if ($set !== '') {
    $sql .= 'INNER JOIN nga_card_printings AS p ON p.card_id = c.card_id
             INNER JOIN nga_card_sets AS s ON s.set_id = p.set_id ';
//}

$sql .= "WHERE (c.name = '{$name}' OR c.name = '~{$name}~')";

if ($set !== '') {
    $sql .= " AND s.short_name = '{$set}'";
}

$result = $db->sql_query($sql);
$allRows = $db->sql_fetchrowset($result);
$row = $allRows[0];

$manaHtml = decodemana($row['mana_cost']);
$cleanedRules = cleanRulesTextForDisplay($row['rules_text']);
$cleanedFlavor = cleanFlavorTextForDisplay($row['flavor_text']);
$cleanedTypes = cleanRulesTextForDisplay($row['types']);
$name = $row['name'];

if ($name[0] === '~' && substr($name, -1) === '~') {
    $name = '<i>' . substr($name, 1, -1) . '</i>';
}

if (isset($row['render_url'])) {
    $renderHTML = "<img class=\"card-render\" src=\"{$row['render_url']}\">";
} else {
    $renderHTML = renderCardHTML(
        $name,
        $manaHtml,
        $row['colors'],
        $cleanedTypes,
        "[" . $row['short_name'] . "]",
        $cleanedRules,
        $cleanedFlavor,
        $row['power'],
        $row['toughness'],
        $row['loyalty']
    );
}

if ($view === 'render') {
    $template->assign_vars(array(
        'S_RENDER_HTML' => $renderHTML,
    ));

    page_header('');

    $template->set_filenames(array(
        'body' => 'library/card_render.html',
    ));

    page_footer();
} else if ($view === 'full') {
    $setLinks = array();    
    foreach ($allRows as $thisRow) {
        $setLinks[] = '<a href="./card_search.php?sets[]=' . $thisRow['short_name'] . '">' . $thisRow['set_name'] . '</a>';
    }
    $setHtml = implode(', ', $setLinks);

    $template->assign_vars(array(
        'S_RENDER_HTML' => $renderHTML,
        'S_NAME' => $name,
        'S_MANA' => $manaHtml,
        'S_CMC'  => $row['cmc'],
        'S_TYPES' => $cleanedTypes,
        'S_RULES_TEXT' => $cleanedRules,
        'S_FLAVOR_TEXT' => $cleanedFlavor,
        'S_IS_CREATURE' => ($row['power'] && $row['toughness']),
        'S_SIZE' => ($row['loyalty'] != null) ? $row['loyalty'] : ($row['power'] . '/' . $row['toughness']),
        'S_SETS' => $setHtml,
        'S_RARITY' => $row['rarity'],
        'S_IS_BANNED' => $row['is_banned']
    ));


    page_header($row['name']);

    $template->set_filenames(array(
        'body' => 'library/view_card.html',
    ));


    page_footer();
}

$db->sql_freeresult($result);
