<?php
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_nga.php');

/* Translates between Cockatrice-format color codes and database-format color codes */
static $COLOR_TRANSLATIONS = array(
    'W' => 'white',
    'U' => 'blue',
    'B' => 'black',
    'R' => 'red',
    'G' => 'green'
);

/* Translates between Cockatrice-format rarity codes and database-format rarit codes */
static $RARITY_TRANSLATIONS = array(
    'C' => 'common',
    'U' => 'uncommon',
    'R' => 'rare',
    'M' => 'mythic',
    'BL' => 'basic'
);

function createFromCockatriceFile($filename) {
    global $db, $COLOR_TRANSLATIONS, $RARITY_TRANSLATIONS; 

    $data = simplexml_load_file($filename);

    // build and insert a list of sets indexed by short name
    $sets = array();

    foreach ($data->sets->set as $set) {
        // TODO: error-checking of all kinds
        // Specifically, look at whether a set with the given shortname already exists
        $shortName = (string)$set->name;
        $sets[ $shortName ] = array(
            'short_name' => $shortName,
            'name' => (string)($set->longname),
            'is_public' => true
        );

        $sql = 'INSERT INTO nga_card_sets ' . $db->sql_build_array('INSERT', $sets[$shortName]);
        echo 'Found set: ', (string)($set->longname), '<br>';

        $db->sql_query($sql);

        $sets[ $shortName ]['set_id'] = $db->sql_nextid();
    }
    // sets have been inserted

    // go through the list of cards, inserting each
    foreach ($data->cards->card as $card) {
        // compute some values we need
        $manacost = normalizeManaCost($card->manacost);

        $colors = array();
        foreach ($card->color as $color) {
            $colors[] = $COLOR_TRANSLATIONS[ (string)$color ];
        }
        $colorString = implode(',', $colors);

        $rulesText = cleanRulesTextForDB($card->text);
        $stats = getPowerAndToughness($card->pt);

        $loyalty = null;
        if ($card->loyalty != null) {
            $loyalty = (string)$card->loyalty;
        }

        // build a handy table from which the SQL statement will be built
        $card_ary = array(
            'name' => (string)$card->name,
            'mana_cost' => $manacost,
            'cmc' => computeCMC($manacost),
            'colors' => $colorString,
            'types' => (string)$card->type,
            'rules_text' => $rulesText,
            'power' => $stats[0],
            'toughness' => $stats[2],
            'power_num' => $stats[1],
            'toughness_num' => $stats[3],
            'loyalty' => $loyalty
        );

        if (strpos( (string)$card->text, '~Banned' ) !== false) {
            $card_ary['is_banned'] = 1;
        }

        $sql = 'INSERT INTO nga_cards ' . $db->sql_build_array('INSERT', $card_ary);
        echo 'Adding card ', (string)($card->name), '.';

        $db->sql_query($sql);
        $card_id = $db->sql_nextid();

        foreach ($card->set as $set)
        {
            $set_id = $sets[ (string)$set ]['set_id'];

            $printing_ary = array(
                'card_id' => $card_id,
                'set_id'  => $set_id
            );

            $attr = $set->attributes();
            if (isset($attr['picURL'])) {
                    $printing_ary['render_url'] = (string)$attr['picURL'];
            }
            if (isset($attr['rarity'])) {
                $elt = (string)$attr['rarity'];
                $printing_ary['rarity'] = $RARITY_TRANSLATIONS[ $elt ];
                echo '.. it is a "' . $elt . '" => ' . $printing_ary['rarity'];
            }

            $sql = 'INSERT INTO nga_card_printings ' . $db->sql_build_array('INSERT', $printing_ary);
            echo $sql;
            $db->sql_query($sql);
        }
        
        echo '<br>';
    }
    // cards have been inserted
}


function addFlavortext($filename)
{
        global $db;

        $data = simplexml_load_file($filename);

        foreach ($data->card as $card)
        {
                $name = $db->sql_escape((string)$card->name);
                $text = (string)$card->flavor;
                $params = array( 'flavor_text' => $text );

                $sql = 'UPDATE nga_cards ' .
                       'SET ' . $db->sql_build_array('UPDATE', $params) .
                       "WHERE name = '$name'";

                echo "Adding flavor text for $name<br>";

                $db->sql_query($sql);
        }
}

//createFromCockatriceFile('cards.xml');
addFlavortext('ngaflavor.xml');

/**
  *     1. X, Y, Z
  *     2. Numeric
  *     3. 2-brid, in normal order for WUBRG
  *     4. hybrid, in WUBRG order by 'upper' color
  *     5. snow
  *     6. phyrexian symbols, in normal order for WUBRG
  *     7. normal colored symbols, in normal order for WUBRG
 */
/*
$tests = array(
    '1', '(10)', 'w', '(wu)', '(wu)(uw)', 'wu', 'ug', 'gb','(wu)(ur)(ug)', 'rx', 'yrux', 'gbu', 'rub', 
    'zwbux(pw)(pu)(pg)(pr)sssywwbx(2/u)(2u)(ur)(rg)(gu)3'
);

foreach($tests as $test) {
    echo normalizeManaCost($test), '     ', computeCMC($test), '<br>';
}
*/

?>
