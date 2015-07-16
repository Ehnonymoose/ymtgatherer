<?php

/****************************/
/* SEARCH-RELATED FUNCTIONS */
/****************************/

/* 
Constructs a list of SQL-syntax conditions to search for given the input search string $name.

e.g    gTSC('"foo bar" baz', 'name')    =>    'name LIKE "%foo bar%" AND name LIKE "%baz%"'
*/
function getTextSearchConditions($search, $fieldName) {
    $tokens = array();

    $search = trim(html_entity_decode($search, ENT_QUOTES));
    $start = 0;

    while ($start < strlen($search)) {
        if ($search[$start] === " ") {
            $start += 1;
            continue;
        } else if ($search[$start] === "\"") {
            // quote-delimited string
            $end = strpos($search, "\"", $start + 1);

            if ($end === FALSE) {
                $end = strlen($search);
            }

            $searchStr = substr($search, $start + 1, $end - $start - 1);
            $condition = "{$fieldName} LIKE \"%{$searchStr}%\"";

            $start = $end + 2;  // skip past the end-quote and the space that probably follows it
        } else {
            // space-delimited string
            $end = strpos($search, ' ', $start);

            if ($end === FALSE) {
                $end = strlen($search);
            }

            $searchStr = substr($search, $start, $end - $start);

            $condition = "{$fieldName} LIKE \"%{$searchStr}%\"";
            $start = $end + 1;
        }

        $tokens[] = $condition;
    }

    if (count($tokens) > 0) {
        return '(' . implode(' AND ', $tokens) . ')';
    } else {
        return '';
    }
}


/********************************/
/* END SEARCH-RELATED FUNCTIONS */
/********************************/



/***********************/
/* CARD-VIEW FUNCTIONS */
/***********************/

function decodemana($mana)
{
    $translationList = array(
    /* basic symbols */     'w' => 'w', 'u' => 'u', 'b' => 'b', 'r' => 'r', 'g' => 'g', 's' => 's',
    /* numeric symbols */   '0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10', '11' => '11', '12' => '12', '13' => '13', '14' => '14', '15' => '15', '16' => '16', '20' => '20', '100' => '100', '1000000' => '1000000', 'inf' => 'infinity',
    /* allied hybrid */     'wu' => 'wu', 'uw' => 'wu', 'ub' => 'ub', 'bu' => 'ub', 'br' => 'br', 'rb' => 'br', 'rg' => 'rg', 'gr' => 'rg', 'gw' => 'gw', 'wg' => 'gw',
    /* enemy hybrid */      'wb' => 'wb', 'bw' => 'wb', 'ur' => 'ur', 'ru' => 'ur', 'bg' => 'bg', 'gb' => 'bg', 'rw' => 'rw', 'wr' => 'rw', 'gu' => 'gu', 'ug' => 'gu',
    /* 2brid */             '2w' => '2w', '2u' => '2u', '2b' => '2b', '2r' => '2r', '2g' => '2g',
    /* phyrexian */          'p' => 'p', 'pw' => 'pw', 'pu' => 'pu', 'pb' => 'pb', 'pr' => 'pr', 'pg' => 'pg',
    /* X, Y, Z */            'x' => 'x', 'y' => 'y', 'z' => 'z',
    /* tap, untap */         't' => 't', 'tap' => 't', 'untap' => 'q', 'q' => 'q'
    );

    $html = '';

    $mana = html_entity_decode($mana, ENT_QUOTES);

    $token_start = 0;
    $token_end = 0;
    $length = strlen($mana);
    while ($token_start < $length)
    {
        if ($mana[$token_start] === '(')
        {
            # this is a token wrapped in parens. find the r-paren
            $rparen = strpos($mana, ')', $token_start);
            if ($rparen === false)
            {
                # didn't find an rparen!
                $token = '(';
                $token_start += 1;
            } else {
                # found an rparen!
                $token = substr($mana, $token_start + 1, $rparen - $token_start - 1);
                $token_start = $rparen + 1;
            }
        } else {
            $token = $mana[$token_start];
            $token_start += 1;
        }

        $cleaned = strtolower($token);
        $cleaned = preg_replace("/[^A-Za-z0-9 ]/", '', $cleaned);

        if ( array_key_exists($cleaned, $translationList) )
        {
            $html .= '<img src="http://forum.nogoblinsallowed.com/images/smilies/mana/' . $translationList[$cleaned] . '.png"/>';
        }
        else
        {
            $html .= $token;
        }
    }

    return $html;
}


function cleanFlavorTextForDisplay($text) {
    // Clean up flavor text by:
    //  - replace newlines with <br/>s
    //  - replace ~foo~ by </i>foo<i>  (since flavor text is by default italic)

    $cleanedText = '<i>' . $text . '</i>';
    $cleanedText = str_replace("\n", "<br/>", $cleanedText);
    $cleanedText = preg_replace('/~(.*?)~/', '</i>\1<i>', $cleanedText);

    return $cleanedText;
}

function replaceManaSymbols($text)
{
    return preg_replace_callback(
        "/{([a-zA-Z0-9\/]+)}/", 
        function ($matches) {
            $short = preg_replace('/[^a-zA-Z0-9]+/', '', $matches[1]);
            return '<img src="http://forum.nogoblinsallowed.com/images/smilies/mana/' . strtolower($short) . '.png">';
        }, 
        $text
    );
}
function cleanRulesTextForDisplay($text) {
    // Clean up card text by:
    //  - replacing newlines with <br/>s
    //  - replacing {foo} with the appropriate mana symbols
    //  - making parenthetical things italic
    //  - replacing ~foo~ with italicized text
    //  - making //foo// purple
    $cleanedText = '<p>' . $text . '</p>';
    $cleanedText = str_replace("\n", '</p><p>', $cleanedText);
    $cleanedText = preg_replace('/(\(.*?\))/', '<i>\1</i>', $cleanedText);
    $cleanedText = replaceManaSymbols($cleanedText);
    $cleanedText = preg_replace('/~(.*?)~/', '<i>\1</i>', $cleanedText);
    $cleanedText = preg_replace('# //(.*?)//#', ' <span style="color:purple;">\1</span>', $cleanedText);

    return $cleanedText;
}

function renderCardHTML($name, $mana, $colors, $types, $symbol, $rules_text, $flavor_text, $power, $toughness, $loyalty) {
    if (strchr($colors, ',') > -1) {
        $colors = 'gold';
    } else if (strlen($colors) == 0) {
        $colors = 'colorless';
    }

    if (substr($name, 0, 1) === '~' && substr($name, -1, 1) === '~') {
        $name = "<i>" . substr($name, 1, -1) . "</i>";
    }

    $html = " 
    <div class=\"nga-card card-{$colors}\">
    <div class=\"curved-box\">
        <span style=\"font-weight:bold;float:left;\">{$name}</span><span style=\"float:right\">{$mana}</span>
    </div>
    <div class=\"inset-box card-image\">
        <!-- image -->
    </div>
    <div class=\"curved-box\">
        <span style=\"float:left\">{$types}</span>";

    if ($symbol) {
        $html .= "<span style=\"float:right\">{$symbol}</span>";
    }

    $html .= "
    </div>

    <div class=\"inset-box text-box\">
        <div style=\"display: table-cell; vertical-align: middle;\">
            <div class=\"rules-text\">
                {$rules_text}
            </div>
            <div class=\"flavor-text\">
                {$flavor_text}
            </div>
        </div>";

    if ($power !== NULL && $toughness !== NULL) {
        $html .= "
        <div class=\"curved-box creature-size\">
            {$power}/{$toughness}
        </div>";
    } else if ($loyalty != NULL) {
        $html .= "
        <div class=\"curved-box creature-size\">
            {$loyalty}
        </div>";
    }

    $html .= "
    </div>
    </div>";

    return $html;
}

function cleanRulesTextForDB($text) {
    $text = (string)$text;

    $lines = explode("\n", $text);
    $result = '';
    $firstLine = true;

    foreach ($lines as $line) {
        $line = trim($line);
        if (strlen($line) > 0) {
            if (!$firstLine) {
                $result .= "\n";
            } else {
                $firstLine = false;
            }

            $result .= $line;
        }
    }

    return trim($result);
}

/** format of result:  array( display_power, numeric_power, display_toughness, numeric_toughness) */
function getPowerAndToughness($pt) {
    $pt = (string)$pt;
    if ($pt === '') {
        return array(NULL, NULL, NULL, NULL);
    }

    $stats = explode('/', $pt);

    return array(
        $stats[0],
        is_numeric($stats[0]) ? (int)($stats[0]) : 0,
        $stats[1],
        is_numeric($stats[0]) ? (int)($stats[1]) : 0
    );
}

/***************************/
/* END CARD-VIEW FUNCTIONS */
/***************************/


/*************************************/
/* BEGIN MANA COST-RELATED FUNCTIONS */
/*************************************/


/* Translates between mana tokens and their CMCs */
static $MANACOST_TO_CMC = array(
        /* basic symbols */     'w' => 1, 'u' => 1, 'b' => 1, 'r' => 1, 'g' => 1, 's' => 1,
        /* numeric symbols */   '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, '10' => 10, '11' => 11, '12' => 12, '13' => 13, '14' => 14, '15' => 15, '16' => 16, '100' => 100, '1000000' => 1000000,
        /* allied hybrid */     'wu' => 1, 'ub' => 1, 'br' => 1, 'rg' => 1, 'gw' => 1,
        /* enemy hybrid */      'wb' => 1, 'ur' => 1, 'bg' => 1, 'rw' => 1, 'gu' => 1,
        /* 2brid */             '2w' => 2, '2u' => 2, '2b' => 2, '2r' => 2, '2g' => 2,
        /* phyrexian */         'pw' => 1, 'pu' => 1, 'pb' => 1, 'pr' => 1, 'pg' => 1,
        /* X, Y, Z */           'x' => 0, 'y' => 0, 'z' => 0
);

/* all valid tokens in mana costs, and their normalized version */
static $MANACOST_NORMALIZATIONS = array(
    /* basic symbols */     'w' => 'w', 'u' => 'u', 'b' => 'b', 'r' => 'r', 'g' => 'g', 's' => 's',
    /* numeric symbols */   '0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10', '11' => '11', '12' => '12', '13' => '13', '14' => '14', '15' => '15', '16' => '16', '100' => '100', '1000000' => '1000000',
    /* allied hybrid */     'wu' => 'wu', 'uw' => 'wu', 'ub' => 'ub', 'bu' => 'ub', 'br' => 'br', 'rb' => 'br', 'rg' => 'rg', 'gr' => 'rg', 'gw' => 'gw', 'wg' => 'gw',
    /* enemy hybrid */      'wb' => 'wb', 'bw' => 'wb', 'ur' => 'ur', 'ru' => 'ur', 'bg' => 'bg', 'gb' => 'bg', 'rw' => 'rw', 'wr' => 'rw', 'gu' => 'gu', 'ug' => 'gu',
    /* 2brid */             '2w' => '2w', '2u' => '2u', '2b' => '2b', '2r' => '2r', '2g' => '2g',
    /* phyrexian */         'pw' => 'pw', 'pu' => 'pu', 'pb' => 'pb', 'pr' => 'pr', 'pg' => 'pg',
    /* X, Y, Z */           'x' => 'x', 'y' => 'y', 'z' => 'z'
);


function tokenizeManaCost($mana) {
    global $MANACOST_NORMALIZATIONS;

    $tokens = array();

    $token_start = 0;
    $token_end = 0;
    $length = strlen($mana);
    $valid = true;
    while ($token_start < $length && $valid)
    {
        if ($mana[$token_start] === '(')
        {
            # this is a token wrapped in parens. find the r-paren
            $rparen = strpos($mana, ')', $token_start);
            if ($rparen === false)
            {
                # didn't find an rparen!
                $valid = false;
                break;

            } else {
                # found an rparen!
                $token = substr($mana, $token_start + 1, $rparen - $token_start - 1);
                $token_start = $rparen + 1;
            }
        } else {
            $token = $mana[$token_start];
            $token_start += 1;
        }

        $cleaned = strtolower($token);
        $cleaned = preg_replace("/[^A-Za-z0-9 ]/", '', $cleaned);

        if (!array_key_exists($cleaned, $MANACOST_NORMALIZATIONS)) {
            $valid = false;
            break;
        }

        $tokens[] = $MANACOST_NORMALIZATIONS[$cleaned];
    }

    if ($valid) {
        return $tokens;
    } else {
        return NULL;
    }
}


function getOrderedTokens($tokens, $order) {
    $result = array();

    if (!is_array($tokens) || !is_array($order)){
        return $result;
    }

    foreach ($order as $ref) {
        foreach ($tokens as $token) {
            if ($ref === $token) {
                $result[] = $ref;
            }
        }
    }

    return $result;
}

function getThreeColorOrder($tokens, $unique, $wubrg) {
    $shard = false;
    for ($first = 0; $first < 5; $first++) {
        if (in_array($wubrg[$first], $unique) && 
            in_array($wubrg[ ($first + 1) % 5 ], $unique) &&
            in_array($wubrg[ ($first + 2) % 5 ], $unique))
        {
            $shard = true;
            break;
        }
    }

    if ($shard) {
        return getOrderedTokens($tokens, array($wubrg[$first], $wubrg[ ($first + 1) % 5 ], $wubrg[ ($first + 2) % 5 ]));
    }

    // OK, it's a wedge.  Find the 2-step start.
    for ($first = 0; $first < 5; $first++) {
        if (in_array($wubrg[$first], $unique) && 
            in_array($wubrg[ ($first + 2) % 5 ], $unique) &&
            in_array($wubrg[ ($first + 4) % 5 ], $unique))
        {
            return getOrderedTokens($tokens, array($wubrg[$first], $wubrg[ ($first + 2) % 5 ], $wubrg[ ($first + 4) % 5 ]));
        }
    }

    // Should never happen
    return NULL;
}

/**
 * Returns all WUBRG symbols in the correct order.
 *
 * Mono-colored: duh
 * Two colors: shortest distance along WUBRG
 * Three colors:
 *    Shard: WUBRG order  (WUB, UBR, BGR, RGW, GWU)
 *    Wedge: skip-2 order (WBG, URW, BGU, RWB, GUR)
 * Four colors: start after the missing one (UBRG, BRGW, RGWU, GWUB)
 * Five colors: WUBRG, duh
 */
function getWUBRGTokens($tokens, $wubrg) {
    // pull out all the tokens we care about
    $wubrg_tokens = array();
    foreach($tokens as $token) {
        if (in_array($token, $wubrg)) {
            $wubrg_tokens[] = $token;
        }
    }

    // determine how many colors we have
    $unique_tokens = array_values(array_unique($wubrg_tokens));
    switch(count($unique_tokens)) {
        case 0:
            $result = array();
            break;
        case 1:
            $result = $wubrg_tokens;
            break;
        case 2:
            $idx0 = array_search($unique_tokens[0], $wubrg);
            $idx1 = array_search($unique_tokens[1], $wubrg);

            if (abs($idx1 - $idx0) > 2) {
                if ($idx0 > $idx1) {
                    $first = $idx0;
                    $second = $idx1;
                } else {
                    $first = $idx1;
                    $second = $idx0;
                }
            } else {
                if ($idx0 > $idx1) {
                    $first = $idx1;
                    $second = $idx0;
                } else {
                    $first = $idx0;
                    $second = $idx1;
                }
            }

            $result = getOrderedTokens($wubrg_tokens, array($wubrg[$first], $wubrg[$second]));
            break;
        case 3:
            $result = getThreeColorOrder($wubrg_tokens, $unique_tokens, $wubrg);
            break;
        case 4:
            $missing = array_values(array_diff($wubrg, $unique_tokens));
            $missing = $missing[0];
            $missing_idx = array_search($missing, $wubrg);

            $result = array();
            for ($i = 1; $i < 5; $i++) {
                $result[] = $wubrg[ ($missing_idx + $i) % count($wubrg) ];
            }
            break;
        case 5:
            $result = getOrderedTokens($wubrg_tokens, $wubrg);
            break;
    }

    return $result;
}

/** Takes in a string representing a mana cost and normalizes it.
  * This means that it takes the tokens and rearranges them in the following order:
  *     1. X, Y, Z
  *     2. Numeric
  *     3. 2-brid, in WUBRG order
  *     4. hybrid, in WUBRG order by 'upper' color
  *     5. snow
  *     6. phyrexian symbols, in normal order for WUBRG
  *     7. normal colored symbols, in normal order for WUBRG
  *
  * Since Wizards has never printed a card with more than three of these (Marisi's Twinclaws) most of this is speculation.
  */ 

function normalizeManaCost($cost) {
    $tokens = tokenizeManaCost( (string)$cost );

    /* This is horrible and I hate it, but don't have a better way to do it */
    $sorted_tokens = getOrderedTokens($tokens, array('x', 'y', 'z'));
    $sorted_tokens = array_merge(
        $sorted_tokens, 
        getOrderedTokens($tokens, array('100000', '100', '16', '15', '14', '13', '12', '11', '10', '9', '8', '7', '6', '5', '4', '3', '2', '1', '0'))
    );
    $sorted_tokens = array_merge(
        $sorted_tokens, 
        getWUBRGTokens($tokens, array('2w', '2u', '2b', '2r', '2g'))
    );
    $sorted_tokens = array_merge(
        $sorted_tokens, 
        getOrderedTokens($tokens, array('wu', 'wb', 'ub', 'ur', 'br', 'bg', 'rg', 'rw', 'gw', 'gu'))
    );
    $sorted_tokens = array_merge(
        $sorted_tokens, 
        getOrderedTokens($tokens, array('s'))
    );
    $sorted_tokens = array_merge(
        $sorted_tokens, 
        getWUBRGTokens($tokens, array('pw', 'pu', 'pb', 'pr', 'pg'))
    );
    $sorted_tokens = array_merge(
        $sorted_tokens, 
        getWUBRGTokens($tokens, array('w', 'u', 'b', 'r', 'g'))
    );


    if (count($sorted_tokens) > 0) {
        return '(' . implode(')(', $sorted_tokens) . ')';
    } else {
        // Should only happen for cards without a mana cost.
        return NULL;
    }
}

function computeCMC($cost) {
    global $MANACOST_TO_CMC;

    $tokens = tokenizeManaCost( (string)$cost );
    
    $total = 0;
    foreach ($tokens as $token) {
        $total += $MANACOST_TO_CMC[ $token ];
    }
    return $total;
}

/********************************************/
/*   END MANA COST-RELATED FUNCTIONS (YAY!) */
/********************************************/


?>
