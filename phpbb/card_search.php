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


// Constants
$results_per_page = 10;
$COMPARATOR_TRANSLATIONS = array(
    'lt' => '<',
    'gt' => '>',
    'lte' => '<=',
    'gte' => '>=', 
    'eq' => '='
);
$COLOR_TRANSLATIONS = array(
    'w' => 'white',
    'u' => 'blue',
    'b' => 'black',
    'r' => 'red', 
    'g' => 'green'
);
$RARITY_TRANSLATIONS = array(
    'common' => 'C',
    'uncommon' => 'U',
    'rare' => 'R',
    'mythic' => 'M',
    'basic' => 'B'
);

// Search variables
$start          = request_var('start', -1);

// Search parameters
$name       = utf8_normalize_nfc(request_var('name', '', true));
$rulesText      = utf8_normalize_nfc(request_var('rules', '', true));
$types          = utf8_normalize_nfc(request_var('types', '', true));
$colors         = request_var('colors', array('' => ''));
$multicolor = request_var('multicolor', 0);
$colorless      = request_var('colorless', 0);
$strict_color   = request_var('color_strict', 0);
$manaCost       = utf8_normalize_nfc(request_var('mana', '', true));
$cmcComparator  = request_var('cmc_compare', 'eq');
$cmc            = request_var('cmc', '');     
$powComparator  = request_var('pow_compare', 'eq');
$pow            = request_var('pow', '');
$touComparator  = request_var('tou_compare', 'eq');
$tou            = request_var('tou', '');
$rarities       = request_var('rarities', array('' => ''));
$sets           = request_var('sets', array('' => ''));
$banned         = request_var('banned', '');

$random         = request_var('random', -1);

if (($start > -1) || $name || $rulesText || $types || $colors || $multicolor || $colorless || $manaCost || 
   ($cmc !== '') || ($pow !== '') || ($tou !== '') || $rarities || $sets || $random == 0 || $random == 1 ||
   ($banned == 'n' || $banned == 'y')) {
    // OK, we have to produce actual search results.

    /*  So, the default value of -1 for $start is in case the following happens:
         - user clicks "submit" with no fields selected
         - user clicks "next page" button (submitting with ?start=20)
         - user clicks "previous page" button (submitting with ?start=0)
        If we default $start to 0, none of the conditions above will be truthy, so 
        it'll show the search page instead of the first page of results.

        So instead we have a flag value for $start meaning "not set", and fix it here.
    */
    if ($start < 0) {
        $start = 0;
    }
    if ($random < 0) {
        $random = 0;
    }

    // Query the database
    $set_where = '';
    $card_tokens = array();

    if (($str = getTextSearchConditions($name, 'c.name')) !== '' ) { $card_tokens[] = $str; }
    if (($str = getTextSearchConditions($rulesText, 'c.rules_text')) !== '' ) { $card_tokens[] = $str; }
    if (($str = getTextSearchConditions($types, 'c.types')) !== '' ) { $card_tokens[] = $str; }

    if ($manaCost) {
        // TODO: check for invalid mana cost
        $card_tokens[] = 'c.mana_cost = "' . normalizeManaCost($manaCost) . '"';
    }

    if ($banned == 'y') {
        $card_tokens[] = 'c.is_banned = 1';
    }  else if  ($banned == 'n') {
        $card_tokens[] = 'c.is_banned = 0';
    }

    if ($pow !== '') {
        if ($powComparator === 'eq') {
            $card_tokens[] = 'c.power = "' . $pow . '"';
        } else {
            // TOOD: check for URL-hacked comparator
            $card_tokens[] = 'c.power_num ' . $COMPARATOR_TRANSLATIONS[$powComparator] . ' ' . $pow;
        }
    }

    if ($tou !== '') {
        if ($touComparator === 'eq') {
            $card_tokens[] = 'c.toughness = "' . $tou . '"';
        } else {
            // TOOD: check for URL-hacked comparator
            $card_tokens[] = 'c.toughness_num ' . $COMPARATOR_TRANSLATIONS[$touComparator] . ' ' . $tou;
        }
    }

    // CMC is always numerical so it is a little nicer than power/toughness
    if ($cmc !== '') {
        // TOOD: check for URL-hacked comparator
        $card_tokens[] = 'c.cmc ' . $COMPARATOR_TRANSLATIONS[$cmcComparator] . ' ' . $cmc;
    }

    // Color(s)!  Treat as 'OR' rather than 'AND' conditions, unless strict
    $color_tests = array();
    foreach ($colors as $color) {
        $color_tests[] = "FIND_IN_SET('" . $COLOR_TRANSLATIONS[$color] . "',c.colors)>0";
    }
    // check for multicolor
    if ($multicolor)
    {
        $color_tests[] = "LOCATE(',',c.colors)>0";
    }

    if ($colorless)
    {
        $color_tests[] = "c.colors = ''";
    }

    if (count($color_tests) > 0) {
        $card_tokens[] = '(' . implode(' OR ', $color_tests) . ')';
    }

    // if necessary add negative tests for excluded colors
    if ($strict_color && count($colors) > 0)
    {   
        $strict_color_tests = array();
        foreach (array_diff( array('w', 'u', 'b', 'r', 'g'), $colors ) as $color)
        {
            $strict_color_tests[] = "FIND_IN_SET('" . $COLOR_TRANSLATIONS[$color] . "',c.colors)=0";
        }
        if (count($strict_color_tests) > 0) {
            $card_tokens[] = '(' . implode(' AND ', $strict_color_tests) . ')';
        }
    }

    // TODO: rarities
    $rarity_tests = array();
    foreach($rarities as $rarity) {
        $rarity_tests[] = "FIND_IN_SET('" . $rarity . "',p.rarity)>0";
    }
    if (count($rarity_tests) > 0) {
        $card_tokens[] = '(' . implode(' OR ', $rarity_tests) . ')';
    }

    // Sets
    $set_joins = ' INNER JOIN nga_card_printings as p on p.card_id = c.card_id';
    if (count($sets) > 0) {
        $set_joins .= ' INNER JOIN nga_card_sets as s on s.set_id = p.set_id';

        $set_tests = array();
        foreach ($sets as $set) {
            $set_tests[] = "s.short_name = '{$set}'";
        }

        $card_tokens[] = '(' . implode(' OR ', $set_tests) . ')';
    }


    $card_where = implode(" AND ", $card_tokens);

    // Get the number of results
    $sql = 'SELECT COUNT(DISTINCT c.card_id) as num_results
            FROM nga_cards as c ' . $set_joins;
    
    if (strlen($card_where) > 0) {
        $sql .= ' WHERE ' . $card_where;
    }

    $result = $db->sql_query($sql);
    $result_count = (int) $db->sql_fetchfield('num_results');

    $db->sql_freeresult($result);

    // Get the results for this page
    $sql = 'SELECT DISTINCT c.name, c.rules_text, c.mana_cost, c.cmc, c.types, c.power, c.toughness, c.loyalty, p.rarity  
            FROM nga_cards as c ' . $set_joins;
    
    if (strlen($card_where) > 0) {
        $sql .= ' WHERE ' . $card_where;
    }

    if ($random === 1) {
        $sql .= ' ORDER BY RAND()';
    } else {
        $sql .= ' ORDER BY c.name';
    }

//    if ($user->data['username'] == 'GobO_Welder') {
//        print_r($sql);
//    }

    $result = $db->sql_query_limit($sql, $random === 1 ? 1 : $results_per_page, $start);
    $cards = $db->sql_fetchrowset($result);

    $db->sql_freeresult($result);

    if ($result_count == 1 || $random === 1) {
        redirect("{$phpbb_root_path}view_card.php?name=" . $cards[0]['name']);
    }

    $page_count   = ceil($result_count / $results_per_page);

    // construct a search URL to be used for pagination.
    $search_url = "{$phpbb_root_path}card_search.$phpEx";
    $argArray = array();

    // simple fields
    if ($name) { $argArray[] = 'name=' . $name; }
    if ($rulesText) { $argArray[] = 'rules=' . $rulesText; }
    if ($types) { $argArray[] = 'types=' . $types; }
    if ($manaCost) { $argArray[] = 'mana=' . $manaCost; }
    if ($random) { $argArray[] = 'random=' . $random; }
    if ($banned) { $argArray[] = 'banned=' . $banned; }
    if ($multicolor) { $argArray[] = 'multicolor=1'; }
    if ($colorless) { $argArray[] = 'colorless=1'; }
    if ($strict_color) { $argArray[] = 'color_strict=1'; }


    // conditionally-present fields
    if ($cmc !== '') {
        $argArray[] = 'cmc_compare=' . $cmcComparator;
        $argArray[] = 'cmc=' . $cmc;
    }
    if ($pow !== '') {
        $argArray[] = 'pow_compare=' . $powComparator;
        $argArray[] = 'pow=' . $pow;
    }
    if ($tou !== '') {
        $argArray[] = 'tou_compare=' . $touComparator;
        $argArray[] = 'tou=' . $tou;
    }

    // array fields
    foreach ($rarities as $r) {
        $argArray[] = 'rarities[]=' . $r;
    }
    foreach ($sets as $s) {
        $argArray[] = 'sets[]=' . $s;
    }
    foreach ($colors as $c) {
        $argArray[] = 'colors[]=' . $c;
    }

    $argString = implode('&amp;', $argArray);
    if (strlen($argString) !== 0) {
        $search_url .= '?' . $argString;
    }

    // Yay, finally done.


    $template->assign_vars(array(
        'SEARCH_RESULT_COUNT'   => $result_count,
        'SEARCH_RESULT_START'   => 1 + $start,
        'SEARCH_RESULT_END'     => min($start + $results_per_page, $result_count),
        'PAGINATION'            => generate_pagination($search_url, $result_count, $results_per_page, $start),
    ));

    foreach ($cards as $row) {
        $manaCost = decodemana($row['mana_cost']);
        $isCreature = $row['power'] != NULL && $row['toughness'] != NULL ? true : false;

        $template->assign_block_vars('searchresults', array(
            'CARD_NAME'   =>  $row['name'],
            'CARD_MANA'   =>  $manaCost,
            'CARD_CMC'    =>  $row['cmc'],
            'CARD_TYPES'  =>  replaceManaSymbols($row['types']),
            'S_IS_CREATURE' =>  $isCreature,
            'CARD_POWER'  =>  $row['power'],
            'CARD_TOUGHNESS' => $row['toughness'],
            'CARD_TEXT'   =>  cleanRulesTextForDisplay($row['rules_text']),
            'CARD_SET'    =>  'default',
            'CARD_RARITY' =>  $RARITY_TRANSLATIONS[$row['rarity']],
            'CARD_LOYALTY' => $row['loyalty'],
            'URL'		  =>  './view_card.php?name=' . urlencode($row['name']),
        ));
    }


    page_header('NGA Card Search Results');

    $template->set_filenames(array(
        'body' => 'library/card_search_results_body.html',
    ));

    page_footer();

} else {
    // No search is being performed yet. Show the search page.

    // Get a list of all sets
    // TODO: allow set owners to see their own sets
    $sql = 'SELECT * FROM nga_card_sets WHERE nga_card_sets.is_public = TRUE';
    $result = $db->sql_query($sql);

    $set_list = '';
    while ($row = $db->sql_fetchrow($result)) {
        $set_list .= '<option value="' . $row['short_name'] . '">' . $row['name'] . '</option>';
    }

    $db->sql_freeresult($result);

    $template->assign_vars(array(
        'S_SEARCH_ACTION' => append_sid("./card_search.$phpEx"),
        'S_SET_LIST'      => $set_list,
    ));

    page_header('NGA Card Search');

    $template->set_filenames(array(
        'body' => 'library/card_search_body.html',
    ));

    page_footer();
}

?>
