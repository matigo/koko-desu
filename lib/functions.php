<?php

/**
 * @author Jason F. Irwin
 *
 * A General Module File with Core Functions that are Called Throughout the Application
 */

    /**
     * Function checks a value and returns a numeric value
     *  Note: Non-Numeric Values will return 0
     */
    function nullInt($number, $default = 0) {
        $number = filter_var($number, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $rVal = $default;

        if ( is_numeric($number) ) { $rVal = $number; }
        if ( $rVal == 0 && $default > 0 ) {
            $rVal = $default;
        }

        // Return the Numeric Value
        return floatval($rVal);
    }

    /**
     * Function checks a value and returns a string
     */
    function NoNull( $string, $default = '' ) {
        if ( is_object($default) ) { $default = ''; }
        if ( is_array($default) ) { $default = ''; }
        if ( is_null($default) ) { $default = ''; }
        if ( is_bool($default) ) { $default = ''; }

        if ( is_object($string) ) { $string = ''; }
        if ( is_array($string) ) { $string = ''; }
        if ( is_null($string) ) { $string = ''; }
        if ( is_bool($string) ) { $string = ''; }

        $ReplStr = array( '＃' => "#", urldecode('%EF%B8%8F') => ' ', '　' => ' ', );
        $rVal = $default;

        /* Pre-Process the String */
        $string = str_replace(array_keys($ReplStr), array_values($ReplStr), $string);
        $string = preg_replace('/^[\s\x00]+|[\s\x00]+$/u', '', $string);

        /* Let's do some trimming and, if necessary, return defaults */
        if ( is_string($string) ) { $rVal = trim($string); }
        if ( mb_strlen($rVal) <= 0 && mb_strlen($default) > 0 ) { $rVal = $default; }

        /* Return the String Value */
        return $rVal;
    }

    /**
     * Function Returns a Boolean Response based on the Enumerated
     *  Value Passed
     */
    function YNBool( $val ) {
        $valids = array( 'true', 'yes', 'y', 't', 'on', '1', 1 );
        if ( is_bool($val) ) { return $val; }
        return in_array(strtolower($val), $valids);
    }

    /**
     * Function Returns a YN Value based on the Boolean Passed
     */
    function BoolYN( $val ) {
        if ( is_bool($val) ) { return ( $val ) ? 'Y' : 'N'; }
        $valids = array( 'true', 'yes', 'y', 't', 'on', '1', 1 );
        return ( in_array(strtolower($val), $valids) ) ? 'Y' : 'N';
    }

    /**
     *  Function returns a Unix timestamp in a format that is prepped for API output
     *      or, if within 1000 seconds of Epoch, an unhappy boolean
     */
    function apiDate( $unixtime, $format = "Y-m-d\TH:i:s\Z" ) {
        $ts = nullInt($unixtime);
        if( $ts >= 0 && $ts <= 1000 ) { return false; }

        switch ( strtolower(NoNull($format)) ) {
            case 'unix':
            case 'int':
            case 'u':
                return $ts;
                break;

            default:
                /* Carry On */
        }

        /* Here is the default return string */
        return date("Y-m-d\TH:i:s\Z", $ts);
    }

    /**
     *  Function converts a recieved time from the Account-holder's timezone to UTC
     */
    function getUTCTimestamp( $datetime, $timezone = '' ) {
        if ( defined('TIMEZONE') === false ) { define('TIMEZONE', 'UTC'); }
        date_default_timezone_set(NoNull($tzone, NoNull(TIMEZONE, 'UTC')));

        if ( validateDate($datetime, 'Y-m-d H:i:s') ) { return gmdate('Y-m-d H:i:s', strtotime($datetime)); }
        if ( validateDate($datetime, 'Y-m-d H:i') ) { return gmdate('Y-m-d H:i:s', strtotime($datetime)); }
        if ( validateDate($datetime, 'Y-m-d') ) { return gmdate('Y-m-d H:i:s', strtotime($datetime)); }

        /* If we're here, the date is incomplete */
        return '';
    }

    /**
     *  Function pads an integer with leading zeroes
     *
     *  Note: this is mainly used for internal directory naming
     */
    function paddNumber( $num, $length = 8 ) {
        if ( nullInt($length) > 64 ) { $length = 64; }
        if ( nullInt($length) <= 0 ) { $length = 8; }
        if ( nullInt($num) <= 0 ) { return ''; }

        $val = NoNull(substr(str_repeat('0', $length) . nullInt($num), ($length * -1)));
        if ( $val == str_repeat('0', $length) ) { return ''; }
        return $val;
    }

    /**
     *  Function converts a given time string to System time
     */
    function getSystemDateTime( $datetime, $timezone ) {
        if ( defined('TIMEZONE') === false ) { define('TIMEZONE', 'UTC'); }
        if ( $timezone == TIMEZONE ) { return $datetime; }

        $dt = new DateTime($datetime, new DateTimeZone($timezone));
        $dt->setTimezone(new DateTimeZone(TIMEZONE));
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     *  Function converts a given system time string to local time
     */
    function getLocalDateTime( $datetime, $timezone ) {
        if ( defined('TIMEZONE') === false ) { define('TIMEZONE', 'UTC'); }
        if ( $timezone == TIMEZONE ) { return $datetime; }

        $dt = new DateTime($datetime, new DateTimeZone(TIMEZONE));
        $dt->setTimezone(new DateTimeZone($timezone));
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     *  Function converts a UTC timestamp to a readable date/time string in another timezone
     */
    function getDateTimeFromUTC( $timestamp, $timezone = '', $format = "l F jS, Y h:i A" ) {
        if ( defined('TIMEZONE') === false ) { define('TIMEZONE', 'UTC'); }
        $propTZ = NoNull($timezone, NoNull(TIMEZONE, 'UTC'));
        $currTZ = date_default_timezone_get();
        $ts = nullInt($timestamp);

        /* Set the Time Zone only if required */
        if ( $currTZ != $propTZ ) { date_default_timezone_set($propTZ); }

        /* Ensure the format is logical */
        if ( NoNull($format) == '' ) { $format = "l F jS, Y h:i A"; }

        return date($format, $ts);
    }

    /**
     *  Function constructs a complete HTML element based on the data provided
     */
    function buildElement( $tag, $html = '', $classes = false, $data = false ) {
        if ( mb_strlen(NoNull($tag)) <= 0 ) { return ''; }
        $tagAttribs = '';

        /* Do we have any classes to apply? */
        if ( is_array($classes) && count($classes) > 0 ) {
            if ( mb_strlen(NoNull($tagAttribs)) > 0 ) { $tagAttribs .= ' '; }
            $tagAttribs .= 'class="' . implode(' ', $classes) . '"';
        }

        /* Are there any data attributes? */
        if ( is_array($data) && count($data) > 0 ) {
            foreach ( $data as $key=>$val ) {
                /* Values can be empty strings, but keys cannot */
                if ( mb_strlen(NoNull($key)) > 0 ) {
                    $tagAttribs .= ' ' . strtolower($key) . '="' . NoNull($val) . '"';
                }
            }
        }

        /* Return the output */
        $tag = strtolower($tag);
        if ( mb_strlen(NoNull($html)) > 0 ) {
            return '<' . $tag . ((mb_strlen(NoNull($tagAttribs)) > 0) ? ' ' . NoNull($tagAttribs) : '') . '>' .
                        NoNull(strip_tags($html, '<blockquote><strong><em><ol><ul><li><br><a><p><span><label><code><pre><img>')) .
                   '</' . $tag . '>';
        } else {
            return '<' . $tag . ((mb_strlen(NoNull($tagAttribs)) > 0) ? ' ' . NoNull($tagAttribs) : '') . ' />';
        }
    }

    /**
     *  Function builds and returns a random Japanese name based on the sex provided
     */
    function getRandomName( $sex = 'm' ) {
        $surnames = '佐藤, 鈴木, 高橋, 田中, 伊藤, 渡辺, 山本, 中村, 小林, 加藤, 吉田, 山田, 佐々木, 山口, 松本, 井上, 木村, 林, 清水, 山崎, 阿部, 森, 池田, 橋本, 山下, 石川, 中島, 前田, 藤田, 小川, 岡田, 後藤, 長谷川, 村上, 石井, 近藤, 遠藤, 青木, 斎藤, 坂本, 福田, 太田, 西村, 三浦, 藤井, 藤原, 岡本, 松田, 中野, 原田, 小野, 田村, 竹内, 金子, 中川, 和田, 中山, 石田, 上田, 森田, 原, 酒井, 工藤, 横山, 小島, 永井, 高木, 安藤, 宮崎, 宮本, 内田, 橋本, 平野, 柴田, 渡部, 野口, 松井, 大塚, 古川, 上野, 菅原, 小松, 吉川, 山中, 本田, 小山, 平田, 関, 杉山, 高田, 藤本, 永田, 宮田, 平山, 大西, 川口, 川上, 桜井, 久保, 水野, 高島, 片山, 中田, 武田, 谷口, 西田, 安田, 野村, 白石, 青山, 川村, 松浦, 土屋, 堀, 植田, 望月, 黒田, 長井, 村田, 星野, 増田, 菊地, 南, 上原, 大久保, 大野, 田口, 丸山, 今井, 多田, 小池, 大島, 本間, 川崎, 赤坂, 島田, 西山, 成田, 辻, 平松, 大谷, 小田, 吉岡, 田辺, 木下, 岩田, 北村, 沢田, 野田, 松下, 児玉, 谷, 菅野, 長島, 吉村, 大森, 小倉, 宮川, 大川, 秋山, 栗原, 浜田, 三宅, 川島, 井口, 大谷, 樋口, 萩原, 河合, 神田, 北川, 飯田, 上村, 宮原, 大村, 小林, 福島, 中原, 嶋田, 野中, 河村, 江口, 宮城, 池上, 黒川, 若林, 船橋, 富田, 矢野, 吉野, 大竹, 岡崎, 柴山, 野島, 白井, 木本, 安井, 谷川, 相沢, 江藤, 馬場, 武井, 永尾, 大橋, 笠原, 松岡, 赤木, 井田, 坂田, 松井, 大田, 松岡, 高松, 山根, 村岡, 塚本, 三木, 小西, 三好, 小瀬, 山岸, 岩崎, 鳥居, 佐山, 宇野, 浜野, 真鍋, 黒木, 坂井, 鶴田, 梅田, 岩本, 平岡, 上杉, 古賀, 岩永, 岡島, 荒木, 玉井, 藤沢, 富永, 船木, 東, 東山, 永山, 森下, 滝沢, 川田, 鈴森, 下村, 北野, 長谷, 永松, 寺田, 坂口';
        $f_names = '葵, 陽菜, 結衣, 凛, 美月, 心愛, 陽葵, 結菜, 美桜, 愛, 咲良, 杏, 花音, 莉子, 結愛, ひまり, 七海, 結月, 桜, 陽向, 芽依, 美優, 彩葉, 美咲, 心陽, 奈々, 美結, 優月, 咲希, 結子, 桃花, 碧, 華, 優衣, 凜, 美琴, 梨花, 和奏, 日和, 芽生, 瑠花, 桜子, 遥, 美空, 未来, 心結, 結, 望, 小春, 詩, 悠, 美羽, 美怜, 真央, 千夏, 心春, 夢, 澪, 萌, 彩, 美玲, 紗菜, 瑠衣, 優奈, 光, 千尋, 陽菜乃, 柚葉, 花, 鈴, 心晴, 舞, 紬, 美穂, 春菜, 愛菜, 和花, 美音, 望愛, 杏奈, 咲, 美鈴, 真帆, 琴音, 美来, 桃, 芽衣, 明日香, 佳奈, 若菜, 美緒, 彩音, 柚希, 心音, 芽夢, 香織, 遥香, 優花, 菜月, 詩音, 美桜, 美月';
        $m_names = '翔太, 蓮, 陽翔, 大翔, 湊, 悠真, 蒼, 陽大, 結翔, 悠翔, 陽斗, 陽, 陽向, 陸, 翔, 新, 悠人, 大和, 颯太, 陽輝, 朔太郎, 拓海, 昊, 海翔, 隼人, 優真, 颯真, 瑛太, 陽仁, 凛太郎, 悠生, 悠, 駿, 隼, 輝, 匠, 湊斗, 直樹, 晴, 優斗, 龍之介, 陽央, 樹, 颯, 光希, 涼, 悠希, 瑛士, 陽成, 大地, 壮真, 空, 柊, 慶太, 昴, 怜, 晴翔, 悠翔, 恵太, 快晴, 琉翔, 蒼空, 海斗, 快, 碧, 晴輝, 優, 拓真, 蓮斗, 悠陽, 陽向, 陽琉, 陸翔, 宙, 悠翔, 桜介, 千翔, 颯汰, 柊真, 海人, 涼真, 啓太, 誠, 悠仁, 航, 悠貴, 虎太郎, 総一郎, 昂, 大悟, 翼, 優輝, 蒼大, 陽翔, 隼翔, 凌, 陽翔, 昊翔, 智也, 晃, 真';
        if ( in_array(strtolower($sex), array('f','m')) === false ) { $sex = 'm'; }

        /* Begin building the name */
        $surs = explode(',', $surnames);
        $name = $surs[array_rand($surs)];
        $opts = false;

        switch ( strtolower($sex) ) {
            case 'f':
                $opts = explode(',', $f_names);
                break;

            default:
                $opts = explode(',', $m_names);
        }

        if ( is_array($opts) ) {
            $name .= ' ' . NoNull($opts[array_rand($opts)]);
        }

        /* Return the completed name */
        return NoNull($name);
    }

    /**
     *  Function returns a random interest for a faux profile based on the sex provided
     */
    function getRandomInterest( $sex = 'm' ) {
        $m_list = strtolower('Cars, Sports, Video Games, Technology, Fitness, Camping, Fishing, Hiking, Home Improvement, Barbecuing, Watches, Photography, Sci-Fi Movies, Fantasy Novels, Martial Arts, Woodworking, Business, Finance, History, Gadgets, Motorcycles, Weightlifting, Travel, Beer Tasting, Grilling, Politics, Astronomy, Strategy Games, DIY Projects, Music Production');
        $f_list = strtolower('Fashion, Reading, Yoga, Cooking, Interior Design, Gardening, Photography, Dancing, Painting, Journaling, DIY Crafts, Travel, Aromatherapy, Makeup, Jewelry, Hiking, Baking, Language Learning, Volunteering, Fitness, Music, Meditation, Skincare, Home Decor, Animal Care, Blogging, Book Clubs, Sewing, Nutrition, Social Media');
        if ( in_array(strtolower($sex), array('f','m')) === false ) { $sex = 'm'; }
        $opts = false;

        /* Return a random interest */
        if ( strtolower($sex) == 'f' ) {
            $opts = explode(',', $f_list);
        } else {
            $opts = explode(',', $m_list);
        }
        return NoNull($opts[array_rand($opts)]);
    }

    function getRandomTone() {
        $opts = array('enthusiastic', 'positive', 'friendly', 'appreciative', 'balanced', 'neutral',
                      'insightful', 'helpful', 'casual', 'witty', 'humorous', 'calm', 'respectful');
        return NoNull($opts[array_rand($opts)]);
    }

    /**
     *  Function Scrubs a Name of Unnecessary Characters
     */
    function CleanName( $string ) {
        $ReplStr = array( '（' => '(', '）' => ')', '(' => ' (', '＜' => '(', '＞' => ')',
                          '*' => '', '#' => '', '_' => ' ', '  ' => ' ', '[' => '(', ']' => ')',
                         );
        $text = mb_convert_kana(NoNull($string), 'a', 'UTF-8');

        /* Remove Unnecessary Characters */
        for ( $i = 0; $i < 9; $i++ ) {
            $text = str_ireplace(array_keys($ReplStr), array_values($ReplStr), $text);
        }

        /* Remove Bracketed Characters */
        $text = trim(preg_replace('/\s*\([^)]*\)/', '', $text));

        return NoNull($text);
    }

    /**
     *  Function returns an array of Unique words in a string (ideally for database insertion)
     */
    function UniqueWords( $string ) {
        if ( NoNull($string) == '' ) { return false; }
        $rVal = array();

        /* Replace the Word-linking Characters with empty strings */
        $ReplStr = array( '‘', '’', '“', '”', '—', '-', '"', "'" );
        $text = str_replace($ReplStr, '', $string);

        /* Replace any Word-terminating characters with spaces */
        $punc = array( '!', '.', ',', '&', '?', '<', '>', '_', '(', ')', '*', '/', '$', '#', '%', '|', '\\', '-', '=', ';', ':', '~', '^', '`', '[', ']', '{', '}', '"', "'");
        $text = NoNull(str_replace($punc, ' ', html_entity_decode(strip_tags($text), ENT_COMPAT | ENT_HTML5 | ENT_QUOTES, 'UTF-8')));
        $uniques = array();
        $words = explode(' ', " $text ");
        foreach ( $words as $word ) {
            $word = strtolower(NoNull($word));
            if ( mb_strlen($word) > 1 && mb_strlen($word) <= 64 && in_array($word, $rVal) === false ) { $rVal[] = $word; }
        }

        /* Return an Array of Unique Words (if we have at least one) */
        if ( count($rVal) > 0 ) { return $rVal; }
        return false;
    }

    /**
     *  Function takes a URL, strips out the protocol and any suffix data, then builds a GUID representation so that
     *      it can be compared against other URLs for uniqueness. If the Url is invalid, an unhappy boolean is returned
     */
    function getGuidFromUrl( $url ) {
        $url = NoNull($url);
        if ( mb_strlen($url) <= 5 ) { return false; }

        if ( mb_substr($url, 0, 8) == 'https://' ) { $url = mb_substr($url, 8); }
        if ( mb_substr($url, 0, 7) == 'http://' ) { $url = mb_substr($url, 7); }
        $url = str_replace('//', '/', $url);
        $chkSlash = true;
        $cnt = 0;

        while ( $chkSlash ) {
            if ( $cnt >= 10 ) { return false; }
            if ( mb_substr($url, -1) == '/' ) {
                $url = mb_substr($url, 0, mb_strlen($url) - 1);
            } else {
                $chkSlash = false;
            }
            $cnt++;
        }

        // Construct a GUID for the Site Based on an MD5 of the "clean" URL
        $UrlGuid = substr(md5($url),  0,  8) . '-' .
                   substr(md5($url),  8,  4) . '-' .
                   substr(md5($url), 12,  4) . '-' .
                   substr(md5($url), 16,  4) . '-' .
                   substr(md5($url), 20, 12);

        // If the Url Guid Appears Valid, Return It. Otherwise, Unhappy Boolean
        if ( strlen($UrlGuid) == 36 ) { return $UrlGuid; }
        return false;
    }

    /**
     *  Function determines if there is a GUID in PgSub and returns the right-most value
     */
    function getGuidFromPgSub( $data ) {
        if ( is_array($data) === false || count($data) <= 0 ) { return ''; }

        /* Run through PgSub from 9 to 1 */
        for ( $i = 9; $i > 0; $i-- ) {
            $vv = NoNull($data['PgSub' . $i]);
            if ( mb_strlen($vv) == 36 ) { return $vv; }
        }

        /* If we're here, there is no GUID */
        return '';
    }

    /**
     *  Function returns the last update time of the versions.php file to replace the CSS_VER definition
     */
    function getMetaVersion() {
        $mm = 0;

        /* Get the file's modification time */
        if ( defined('CONF_DIR') ) {
            $verFile = CONF_DIR . '/versions.php';
            if ( file_exists($verFile) ) { $mm = filemtime($verFile); }
        }

        /* Ideally $mm will be populated. But, just in case ... */
        if ( strlen($mm) < 3 ) {
            $rand = substr("abcdef" . getRandomString(6), -6);
            $mm = alphaToInt($rand);
        }

        /* We shouldn't be here, but we need to return *something* */
        return substr("$mm", -6);
    }

    /**
     *  Function Constructs and returns a properly separated meta data array
     */
    function buildMetaArray( $meta ) {
        if ( is_array($meta) ) {
            $data = array();

            foreach ( $meta as $Row ) {
                $segments = explode('.', NoNull($Row['key']));
                $path = array();
                $prop = '';

                /* Ensure the Meta Array is Properly Constructed */
                switch( count($segments) ) {
                    case 4:
                        $k1 = strtolower(NoNull($segments[0]));
                        $k2 = strtolower(NoNull($segments[1]));
                        $k3 = strtolower(NoNull($segments[2]));
                        $k4 = strtolower(NoNull($segments[3]));

                        if ( is_array($data) && array_key_exists($k1, $data) === false ) { $data[$k1] = array(); }
                        if ( is_array($data[$k1]) && array_key_exists($k2, $data[$k1]) === false ) { $data[$k1][$k2] = array(); }
                        if ( is_array($data[$k1][$k2]) && array_key_exists($k3, $data[$k1][$k2]) === false ) { $data[$k1][$k2][$k3] = array(); }

                        if ( is_numeric($Row['value']) ) {
                            $data[$k1][$k2][$k3][$k4] = nullInt($Row['value']);
                        } else {
                            $data[$k1][$k2][$k3][$k4] = NoNull($Row['value']);
                        }
                        break;

                    case 3:
                        $k1 = strtolower(NoNull($segments[0]));
                        $k2 = strtolower(NoNull($segments[1]));
                        $k3 = strtolower(NoNull($segments[2]));

                        if ( is_array($data) && array_key_exists($k1, $data) === false ) { $data[$k1] = array(); }
                        if ( is_array($data[$k1]) && array_key_exists($k2, $data[$k1]) === false ) { $data[$k1][$k2] = array(); }

                        if ( is_numeric($Row['value']) ) {
                            $data[$k1][$k2][$k3] = nullInt($Row['value']);
                        } else {
                            $data[$k1][$k2][$k3] = NoNull($Row['value']);
                        }
                        break;

                    case 2:
                        $k1 = strtolower(NoNull($segments[0]));
                        $k2 = strtolower(NoNull($segments[1]));

                        if ( is_array($data) && array_key_exists($k1, $data) === false ) { $data[$k1] = array(); }

                        if ( is_numeric($Row['value']) ) {
                            $data[$k1][$k2] = nullInt($Row['value']);
                        } else {
                            $data[$k1][$k2] = NoNull($Row['value']);
                        }
                        break;

                    default:
                        /* This is a malformed key */
                }
            }

            /* If we have data, let's return it */
            if ( is_array($data) && count($data) > 0 ) { return $data; }
        }

        /* If we're here, there's nothing to do */
        return false;
    }

    function isValidCronRequest( $sets, $valids ) {
        if ( is_array($valids) === false ) { return false; }
        if ( is_array($sets) === false ) { return false; }

        if ( array_key_exists('key', $sets) && defined('CRON_KEY') ) {
            if ( NoNull($sets['key']) == NoNull(CRON_KEY) ) {
                $route = NoNull($sets['PgRoot']) . '/' . NoNull($sets['PgSub1']);
                return in_array($route, $valids);
            }
        }
        return false;
    }

    /**
     *  Function tries *really* hard to clean up and fix the messed up HTML that comes back from various
     *      sources on account of all the copy/paste that people do, and return the content in a clean
     *      format that can be a little more flexible to work with locally
     */
    function cleanText( $text, $to_markdown = false ) {
        if ( mb_strlen(NoNull($text)) <= 10 ) { return NoNull($text); }

        /* Remove any HTML Element Classes */
        $text = preg_replace('/class=".*?"/', '', $text);

        /* Remove any HTML Element Styles */
        $text = preg_replace('/style=".*?"/', '', $text);

        /* Clean up the HTML Element tags with gaps */
        $tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                       'strong',
                       'em', 'li', 'ol', 'ul',
                       'i', 'p');
        foreach ( $tags as $tag ) {
            $text = str_ireplace("<$tag >", "<$tag>", $text);
        }

        /* Replace Illogical Items */
        $ReplStr = array( '<h1>'    => '<h3>',      '</h1>'     => '</h3>',
                          '<h2>'    => '<h3>',      '</h2>'     => '</h3>',
                          '<i>'     => '*',         '</i>'      => '*',
                          '<br /> *'=> '<br />*',   ' </'       => '</',

                          '<p>&nbsp;</p>'   => "\r\n",
                          '<br /><br />'    => '</p><p>',
                         );
        $text = str_ireplace(array_keys($ReplStr), array_values($ReplStr), $text);

        /* Clean up the Entities */
        $text = html_entity_decode($text);

        $ReplStr = array( '\\\r' => "\r", '\\r' => "\r", '\r' => "\r",
                          '\\\n' => "\n", '\\n' => "\n", '\n' => "\n",
                          '\\\t' => "\t", '\\t' => "\t", '\t' => "\t"
                         );
        $text = str_ireplace(array_keys($ReplStr), array_values($ReplStr), $text);

        /* Strip Tags, allowing just basic ones */
        /* $text = strip_tags($text, '<strong><em><ol><ul><li><h3><h4><h5><h6><br><a><p>'); */

        /* Convert to Markdown (if required) */
        if ( $to_markdown === true ) {
            $text = str_ireplace('<br />* ', "\r\n* ", $text);
            $tags = array( 'h1' => array('### ', "\r\n"),
                           'h2' => array('### ', "\r\n"),
                           'h3' => array('### ', "\r\n"),
                           'h4' => array('#### ', "\r\n"),
                           'h5' => array('##### ', "\r\n"),
                           'h6' => array('###### ', "\r\n"),
                           'strong' => array('**', '**'),
                           'em' => array('*', "*"),
                           'li' => array('* ', ''),
                           'ol' => array("\r\n", "\r\n"),
                           'ul' => array("\r\n", "\r\n"),
                           'i'  => array('*', "\r\n"),
                           'p'  => array('', "\r\n"),
                          );
            foreach ( $tags as $tag=>$md ) {
                $text = str_ireplace(array("<$tag>", "</$tag>"), array($md[0], $md[1]), $text);
            }

            /* If there are any other tags, let's make them ugly with some bracket conversion */
            $ReplStr = array( '<' => '&lt;', '>' => '&gt;' );
            $text = str_replace(array_keys($ReplStr), array_values($ReplStr), $text);

            /* Remove any white space at the end of a line */
            $lines = explode("\n", $text);
            $inli = false;
            $text = '';
            $cnt = 0;

            foreach ( $lines as $line ) {
                $line = NoNull($line);

                /* Continue */
                if ( $line == '' ) { $cnt++; } else { $cnt = 0; }
                if ( $inli === false && $cnt == 0 && strpos($line, '*') == 0 ) { $text .= "\n\n"; }
                $inli = (strpos($line, '*') === false ) ? false : true;
                if ( $cnt <= 1 ) { $text .= NoNull($line) . "\n\n"; }
            }

            /* Clean up the text for excessive returns */
            for ( $i = 99; $i > 2; $i-- ) {
                $nl = str_repeat("\n", $i);
                $text = str_replace($nl, "\n\n", $text);
            }
        }

        /* Return the Cleaned text */
        return NoNull($text);
    }

    /**
     *  Function Checks the validity of a supplied slug and returns something that is relatively safe
     */
    function cleanSlug( $slug ) {
        $ReplStr = array( ' ' => '-', '--' => '-' );
        $dash = '-';
        return NoNull(strtolower(trim(preg_replace('/[\s-]+/', $dash, preg_replace('/[^A-Za-z0-9-]+/', $dash, preg_replace('/[&]/', 'and', preg_replace('/[\']/', '', iconv('UTF-8', 'ASCII//TRANSLIT', NoNull($slug)))))), $dash)));
    }

    /**
     * Function returns a readable Url (not for href or redirect usage)
     */
    function cleanUrl( $url ) {
        $url = strtolower(urldecode(NoNull($url)));
        if ( mb_strlen($url) > 0 ) {
            /* Scrub the visually unnecessary bits */
            $excludes = array('https', 'http', '://wwww.', '://www.', '://m.', '://', 'index.html', 'index.php');
            $url = str_replace($excludes, '', $url) . '?';
            $url = mb_substr($url, 0, mb_strpos($url, '?'));

            /* Replace deprecated URLs */
            $ReplStr = array( 'twitter.com' => 'x.com' );
            $url = str_replace(array_keys($ReplStr), array_values($ReplStr), $url);

            /* Remove trailing slashes */
            $url = rtrim($url, '/?');
        }
        return $url;
    }

    /**
     * Function Verifies if a Given TLD is in the List of Known TLDs and Returns a Boolean
     */
    function isValidTLD( $tld ) {
        $tld = NoNull(str_replace('.', '', strtolower($tld)));
        if ( $tld == '' ) { return false; }

        $cacheOK = reloadValidTLDs();
        $valids = array();

        // Load the Valid TLDs Array
        if ( checkDIRExists( TMP_DIR ) ) {
            $cacheFile = TMP_DIR . '/valid_tlds.data';
            if ( file_exists( $cacheFile ) ) {
                $data = file_get_contents( $cacheFile );
                $valids = unserialize($data);
            }
        }

        // Return a Boolean Response
        return in_array($tld, $valids);
    }

    /**
     * Function Builds the Valid TLDs Cache Array When Appropriate
     */
    function reloadValidTLDs() {
        $masterList = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';
        $cacheFile = TMP_DIR . '/valid_tlds.data';
        $cacheLife = (60 * 60 * 24 * 30);               // 30 Days Life-Span
        $cacheAge = -1;

        $cacheAge = @filemtime($cacheFile);  // returns FALSE if file does not exist
        if (!$cacheAge or ((time() - $cacheAge) >= $cacheLife)) {
            $data = file_get_contents($masterList);
            if ( $data ) {
                $data = str_replace("\r", "\n", $data);
                $lines = explode("\n", $data);
                $valids = array();

                foreach ( $lines as $line ) {
                    // Only Process Lines that Do Not Have Hashes (Comments) and Dashes
                    if ( strpos($line, '#') === false && strpos($line, '-') === false && NoNull($line) != '' ) {
                        if ( NoNull($line) != '' ) { $valids[] = strtolower(NoNull($line)); }
                    }
                }

                if ( !in_array('local', $valids) ) { $valids[] = 'local'; }
                if ( !in_array('test', $valids) ) { $valids[] = 'test'; }
                if ( !in_array('dev', $valids) ) { $valids[] = 'dev'; }

                if ( checkDIRExists( TMP_DIR ) ) {
                    $fh = fopen($cacheFile, 'w');
                    fwrite($fh, serialize($valids));
                    fclose($fh);
                }
            }
        }

        // Return True
        return true;
    }

    /**
     *  Function Deletes all of the Files (Not Directories) in a Specified Location
     */
    function scrubDIR( $DIR ) {
        $FileName = "";
        $Excludes = array( 'rss.cache' );
        $rVal = false;
        $i = 0;

        if (is_dir($DIR)) {
            $objects = scandir($DIR);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    $FileName = $DIR . "/" . $object;
                    if ( filetype($FileName) != "dir" ) {
                        unlink($FileName);
                        $i++;
                    }
                }
            }
            reset($objects);
        }

        // If We've Deleted Some Files, then Set a Happy Return Boolean
        if ( $i > 0 ) { $rVal = true; }

        // Return a Boolean Value
        return $rVal;
    }

    /**
     * Function validates a given date and returns a boolean response
     */
    function validateDate($date, $format = 'Y-m-d') {
        if ( mb_strlen(NoNull($date)) < 10 ) { return false; }

        $dt = DateTime::createFromFormat($format, $date);
        return $dt && $dt->format($format) === $date;
    }

    /**
     * Function validates an Email Address and Returns a Boolean Response
     */
    function validateEmail( $email ) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) { return true; }
        return false;
    }

    /**
     * Function returns the current MicroTime Value
     */
    function getMicroTime() {
        $time = microtime();
        $time = explode(' ', $time);
        $time = $time[1] + $time[0];

        // Return the Time
        return $time;
    }

    /**
     *  Function Generates an XML Element
     */
    function generateXML( $tag_in, $value_in = "", $attribute_in = "" ){
        $rVal = "";
        $attributes_out = "";
        if (is_array($attribute_in)){
            if (count($attribute_in) != 0){
                foreach($attribute_in as $k=>$v) {
                    $attributes_out .= " ".$k."=\"".$v."\"";
                }
            }
        }

        // Return the XML Tag
        return "<".$tag_in."".$attributes_out.((trim($value_in) == "") ? "/>" : ">".$value_in."</".$tag_in.">" );
    }

    function tabSpace( $num ) {
        $rVal = '';
        if ( $num <= 0 ) { return $rVal; }
        for ( $i = 0; $i < $num; $i++ ) { $rVal .= '    '; }

        // Return the Spaces
        return $rVal;
    }

    /**
     *
     */
    function arrayToXML( $array_in ) {
        $rVal = "";
        $attributes = array();

        foreach($array_in as $k=>$v) {
            if ($k[0] == "@"){
                // attribute...
                $attributes[str_replace("@","",$k)] = $v;
            } else {
                if (is_array($v)){
                    $rVal .= generateXML($k,arrayToXML($v),$attributes);
                    $attributes = array();
                } else if (is_bool($v)) {
                    $rVal .= generateXML($k,(($v==true)? "true" : "false"),$attributes);
                    $attributes = array();
                } else {
                    $rVal .= generateXML($k,$v,$attributes);
                    $attributes = array();
                }
            }
        }

        // Return the XML
        return $rVal;
    }

    // Eliminate the White Space and (Optionally) Style Information from a String
    function scrubWhiteSpace( $String, $ScrubStyles = false ) {
        $rVal = $String;

        $rVal = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', "\n", $rVal));
        if ( $ScrubStyles ) {
            $rVal = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $rVal);
        }

        // Return the Trimmed String
        return NoNull($rVal);
    }

    /**
     * Function Returns the Amount of Time that has passed since $UnixTime
     */
    function getTimeSince( $UnixTime ) {
        $rVal = "";

        if ( $UnixTime > 0 ) {
            $time = time() - $UnixTime;

            $tokens = array (
                31536000 => 'year',
                2592000 => 'month',
                604800 => 'week',
                86400 => 'day',
                3600 => 'hour',
                60 => 'minute',
                1 => 'second'
            );

            foreach ($tokens as $unit => $text) {
                if ($time < $unit) continue;
                $numberOfUnits = floor($time / $unit);
                return $numberOfUnits . ' ' . $text . ( ($numberOfUnits > 1) ? 's' : '' );
            }
        }

        // Return the Appropriate Time String
        return $rVal;
    }

    /**
     * Function Returns the Number of Minutes Since $UnixTime
     */
    function getMinutesSince( $UnixTime ) {
        $rVal = 0;

        if ( $UnixTime > 0 ) {
            $time = time() - $UnixTime;
            if ($time > 60) { $rVal = floor($time / 60); }
        }

        // Return the Number of Minutes that have Passed
        return $rVal;
    }

    /**
     * Function Returns the Number of Minutes Since $UnixTime
     */
    function getMinutesUntil( $UnixTime ) {
        $rVal = 0;

        if ( $UnixTime > 0 ) {
            $time =  $UnixTime - time();
            if ($time > 60) { $rVal = floor($time / 60); }
        }

        // Return the Number of Minutes that have Passed
        return $rVal;
    }

    /**
     * Function Returns the a Cleaner Representation fo Data Size
     */
    function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Function returns a random string of X Length
     */
    function getRandomString( $Length = 10, $AsHex = false ) {
        $nextChar = '';
        $rVal = '';

        $chars = ( $AsHex ) ? '0123456789abcdef' : '0123456789abcdefghijklmnopqrstuvwxyz';
        while ( strlen($rVal) < $Length ) {
            $randBool = rand(1, 9);
            $nextChar = ( $randBool > 5 ) ? strtoupper( $chars[mt_rand(0, strlen($chars))] )
                                          : $chars[mt_rand(0, strlen($chars))];

            //Append the next character to the string
            $rVal .= $nextChar;
        }

        // Return the Random String
        return $rVal;
    }

    /**
     * Functions are Used in uksort() Operations
     */
    function arraySortAsc( $a, $b ) {
        if ($a == $b) return 0;
        return ($a > $b) ? -1 : 1;
    }

    function arraySortDesc( $a, $b ) {
        if ($a == $b) return 0;
        return ($a > $b) ? 1 : -1;
    }

    /**
     * Function Determines if String "Starts With" the supplied String
     */
    function startsWith($haystack, $needle) {
        return !strncmp($haystack, $needle, strlen($needle));
    }

    /**
     * Function Determines if String "Ends With" the supplied String
     */
    function endsWith($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) { return true; }

        return (substr($haystack, -$length) === $needle);
    }

    /**
     * Function Confirms a directory exists and makes one if it doesn't
     *      before returning a Boolean
     */
    function checkDIRExists( $dir ){
        if ( file_exists($dir) === false ) {
            if ( mkdir($dir, 755, true) ) { chmod($dir, 0755); }
        }

        /* Return a boolean value */
        return file_exists($dir);
    }

    /**
     * Function Returns the Number of Files contained within a directory
     */
    function countDIRFiles( $DIR ) {
        $rVal = 0;

        // Only check if the directory exists (of course)
        if ( file_exists($DIR) ) {
            foreach ( glob($DIR . "/*.token") as $filename) {
                $rVal += 1;
            }
        }

        // Return the Number of Files
        return $rVal;
    }

    /**
     * Function returns an array from an Object
     */
    function objectToArray($d) {
        if (is_object($d)) {
            $d = get_object_vars($d);
        }

        if (is_array($d)) {
            return array_map(__FUNCTION__, $d);
        }
        else {
            return $d;
        }
    }

    /**
     *  Function Returns a Boolean Stating Whether a String is a Link or Not
     */
    function isValidURL( $text ) {
        if ( strpos($text, '.') > 0 && strpos($text, '.') < strlen($text) ) { return true; }
        return false;
    }

    /**
     *  Function Returns the Protocol (HTTP/HTTPS) Being Used
     *  Updated to resolve a problem when running behind a load balancer
     */
    function getServerProtocol() {
        $rVal = strtolower(NoNull($_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['REQUEST_SCHEME']));
        if ( NoNull($_SERVER['HTTP_CF_VISITOR']) != '' ) {
            $cf_proto = json_decode($_SERVER['HTTP_CF_VISITOR']);
            $cf_array = objectToArray($cf_proto);
            if ( array_key_exists('scheme', $cf_array) ) { $rVal = strtolower($cf_array['scheme']); }
        }
        return strtolower(NoNull($rVal, 'http'));
    }

    /**
     * Function returns a person's IPv4 or IPv6 address
     */
    function getVisitorIPv4() {
        $opts = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        $max = 0;
        $ip = false;

        foreach ( $opts as $opt ) {
            if ( $ip === false && array_key_exists($opt, $_SERVER) ) {
                $ip = filter_var($_SERVER[$opt], FILTER_VALIDATE_IP);

                /* How long is the string? */
                if ( $ip === false ) {
                    $iplen = mb_strlen(NoNull($_SERVER[$opt]));
                    if ( $iplen > $max ) { $max = $iplen; }
                }
            }
        }

        if ( $ip === false ) { $ip = "Invalid IP ($max Characters)"; }

        /* Return the Visitor's IP Address */
        return NoNull($ip);
    }

    function getApiUrl() {
        if ( !defined('API_DOMAIN') ) { define('API_DOMAIN', ''); }

        $apiURL = NoNull(API_DOMAIN);
        if ( $apiURL == '' ) { $apiURL = NoNull($_SERVER['SERVER_NAME']) . '/api'; }

        $Protocol = getServerProtocol();
        return $Protocol . '://' . $apiURL;
    }

    function getCdnUrl() {
        if ( !defined('CDN_DOMAIN') ) { define('CDN_DOMAIN', ''); }

        $cdnURL = NoNull(CDN_DOMAIN);
        if ( $cdnURL == '' ) { $cdnURL = NoNull($_SERVER['SERVER_NAME']) . '/files'; }

        $Protocol = getServerProtocol();
        return $Protocol . '://' . $cdnURL;
    }

    /**
     * Function scrubs a string to ensure it's safe to use in a URL
     */
    function sanitizeURL( $string, $excludeDot = true ) {
        $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
                       "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
                       "â€”", "â€“", ",", "<", ">", "/", "?");
        $cleanFilter = "/[^a-zA-Z0-9-]/";
        if ( $excludeDot ) {
            array_push($strip, ".");
            $cleanFilter = "/[^a-zA-Z0-9-.]/";
        }
        $clean = NoNull(str_replace($strip, "", strip_tags($string)));
        $clean = preg_replace($cleanFilter, "", str_replace(' ', '-', $clean));

        // This isn't cool, but may be necessary for non-Latin Characters
        if ( NoNull($clean) == "" ) { $clean = urlencode($string); }

        //Return the Lower-Case URL
        return strtolower($clean);
    }

    /**
     *  Function Returns a Gravatar URL for a Given Email Address
     *  Note: Code based on source from https://en.gravatar.com/site/implement/images/php/
     */
    function getGravatarURL( $emailAddr, $size = 80, $default = 'mm', $rating = 'g', $img = false, $atts = array() ) {
        $rVal = "";

        if ( NoNull($emailAddr) != "" ) {
            $rVal = "https://gravatar.com/avatar/" . md5( strtolower( NoNull($emailAddr) ) ) . "?s=$size&d=$default&r=$rating";
            if ( $img ) {
                $rVal = '<img src="' . $rVal . '"';
                foreach ( $atts as $key => $val ) {
                    $rVal .= ' ' . $key . '="' . $val . '"';
                }
                $rVal .= ' />';
            }
        }

        // Return the URL
        return $rVal;
    }

    /**
     * Function parses the HTTP Header to extract just the Response code
     */
    function checkHTTPResponse( $header ) {
        $rVal = 0;

        if(preg_match_all('!HTTP/1.1 ([0-9a-zA-Z]*) !', $header, $matches, PREG_SET_ORDER)) {
            foreach($matches as $match) {
                $rVal = nullInt( $match[1] );
            }
        }

        // Return the HTTP Response Code
        return $rVal;
    }

    /**
     * Function parses the HTTP Header into an array and returns the results.
     *
     * Note: HTTP Responses are not included in this array
     */
    function parseHTTPResponse( $header ) {
        $rVal = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));

        // Parse the Fields
        foreach( $fields as $field ) {
            if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
                $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));

                if( isset($rVal[$match[1]]) ) {
                    $rVal[$match[1]] = array($rVal[$match[1]], $match[2]);
                } else {
                    $rVal[$match[1]] = trim($match[2]);
                }
            }
        }

        // Return the Array of Headers
        return $rVal;
    }

    function getCallbackURL( $extras = array() ) {
        $Excludes = array( 'PgRoot', 'PgSub1', 'PgSub2' );
        $rVal = (empty($_SERVER['HTTPS'])) ? "http://" : "https://";
        $rVal .= $_SERVER['SERVER_NAME'];
        $rVal .= ($_SERVER['SERVER_PORT'] == 80 || $_SERVER['SERVER_PORT'] == 443) ? "" : (":".$_SERVER['SERVER_PORT']);
        if ( NoNull($extras['PgRoot']) != "" ) { $rVal .= "/" . NoNull($extras['PgRoot']); }
        if ( NoNull($extras['PgSub1']) != "" ) { $rVal .= "/" . NoNull($extras['PgSub1']); }
        $rVal .= '/?action=callback';

        if ( count($extras) > 0 ) {
            foreach ( $extras as $Key=>$Value ) {
                if ( !in_array($Key, $Excludes) ) {
                    $rVal .= "&" . urlencode($Key) . "=" . urlencode($Value);
                }
            }
        }

        // Return the Appropritate Callback URL
        return $rVal;
    }

    // Function Validates a URL and, if necessary, follows Redirects to Obtain the Proper URL Domain
    function validateURLDomain( $url ) {
        $url = NoNull($url);
        if ( strlen($url) <= 3 ) { return false; }
        if ( strpos($url, '@') !== false ) { return false; }
        $rVal = false;

        /* If the URL validation flag has not been defined, chances are it's not needed */
        if ( defined('VALIDATE_URLS') === false ) { define('VALIDATE_URLS', 0); }

        if ( VALIDATE_URLS > 0 ) {
            $url_pattern = '#(www\.|https?://)?[a-z0-9]+\.[a-z0-9]\S*#i';
            $okHead = array('HTTP/1.0 200 OK', 'HTTP/1.1 200 OK', 'HTTP/2.0 200 OK');
            $fixes = array( 'http//'  => "http://",         'http://http://'   => 'http://',
                            'https//' => "https://",        'https://https://' => 'https://',
                            ','       => '',                'http://https://'  => 'https://',
                           );
            $scrub = array('#', '?', '.', ':', ';');

            if ( mb_strpos($url, '.') !== false && mb_strpos($url, '.') <= (mb_strlen($url) - 1) && NoNull(str_ireplace('.', '', $url)) != '' &&
                 mb_strpos($url, '[') == false && mb_strpos($url, ']') == false ) {
                $clean_word = str_replace("\n", '', strip_tags($url));
                if ( in_array(substr($clean_word, -1), $scrub) ) { $clean_word = substr($clean_word, 0, -1); }

                $url = ((stripos($clean_word, 'http') === false ) ? "http://" : '') . $clean_word;
                $url = str_ireplace(array_keys($fixes), array_values($fixes), $url);
                $headers = false;

                // Ensure We Have a Valid URL Here
                $hdParts = explode('.', $url);
                if ( NoNull($hdParts[count($hdParts) - 1]) != '' ) { $headers = get_headers($url); }

                if ( is_array($headers) ) {
                    $rURL = $url;

                    // Do We Have a Redirect?
                    foreach ($headers as $Row) {
                        if ( mb_strpos(strtolower($Row), 'location') !== false ) {
                            $rURL = NoNull(str_ireplace('location:', '', strtolower($Row)));
                            break;
                        }
                        if ( in_array(NoNull(strtoupper($Row)), $okHead) ) { break; }
                    }

                    $host = parse_url($rURL, PHP_URL_HOST);
                    if ( $host != '' ) { $rVal = strtolower(str_ireplace('www.', '', $host)); }
                }
            }

        } else {
            $hparts = explode('.', parse_url($url, PHP_URL_HOST));
            $domain = '';
            $parts = 0;

            for ( $dd = 0; $dd < count($hparts); $dd++ ) {
                if ( NoNull($hparts[$dd]) != '' ) {
                    $domain = NoNull($hparts[$dd]);
                    $parts++;
                }
            }

            if ( $parts > 1 && isValidTLD($domain) ) {
                $host = parse_url($url, PHP_URL_HOST);
                if ( $host != '' ) { $rVal = strtolower(str_ireplace('www.', '', $host)); }
            }
        }

        // Reutrn the URL
        return $rVal;
    }

    /**
     * Function redirects a visitor to the specified URL
     */
    function redirectTo( $Url, $sets = false ) {
        $RefUrl = NoNull($_SERVER['REQUEST_SCHEME'], 'http') . '://' . NoNull($_SERVER['SERVER_NAME'], $_SERVER['HTTP_HOST']);
        $ReqURI = NoNull($_SERVER['REQUEST_URI'], '/');
        if ( in_array(strtolower($ReqURI), array('/', '')) === false ) {
            $ps = explode('/', strtolower($ReqURI));
            foreach ( $ps as $p ) {
                if ( NoNull($p) != '' ) { $RefUrl .= '/' . NoNull($p); }
            }
        }

        // Set the Redirect Status
        $status = 302;
        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1');
        header($protocol . ' ' . nullInt($status) . ' ' . getHTTPCode($status) );

        // Record the Usage Statistic so that 302s are Counted
        if ( is_array($sets) ) { recordUsageStat($sets, 302, ''); }

        // If we have a Referral URL, Set It
        if ( NoNull($RefUrl) != '' ) { header( "Referer: $RefUrl" ); }

        // Set the Location header record
        header( "Location: $Url" );
        die;
    }

    /** ******************************************************************** *
     *  Text Direction Functions
     ** ******************************************************************** */
    /**
     *  Function is used by the isRTL() function to determine text direction
     */
    function _uniord($u) {
        $k = mb_convert_encoding($u, 'UCS-2LE', 'UTF-8');
        $k1 = ord(substr($k, 0, 1));
        $k2 = ord(substr($k, 1, 1));
        return $k2 * 256 + $k1;
    }

    /**
     *  Function determines if a string should be LTR or RTL
     */
    function isRTL($str) {
        if( mb_detect_encoding($str) !== 'UTF-8' ) {
            $str = mb_convert_encoding($str,mb_detect_encoding($str),'UTF-8');
        }

        /* Check for Hebrew Characters */
        $chk = preg_match("/\p{Hebrew}/u", $str);
        if ( (is_bool($chk) && $chk) || $chk == 1 ) {
            if ( hebrev($str) == $str ) { return true; }
        }

        /* Check for Urdu Characters */
        $chk = preg_match("/\p{Urdu}/u", $str);
        if ( (is_bool($chk) && $chk) || $chk == 1 ) { return true; }

        /* Check for Persian Characters */
        $chk = preg_match("/\p{Persian}/u", $str);
        if ( (is_bool($chk) && $chk) || $chk == 1 ) { return true; }

        /* Check for Arabic Characters */
        preg_match_all('/.|\n/u', $str, $matches);
        $chars = $matches[0];
        $arabic_count = 0;
        $latin_count = 0;
        $total_count = 0;
        foreach($chars as $char) {
            $pos = $this->_uniord($char);

            if($pos >= 1536 && $pos <= 1791) {
                $arabic_count++;
            } else if($pos > 123 && $pos < 123) {
                $latin_count++;
            }
            $total_count++;
        }

        /* If we have 60% or more Arabic characters, it's probably RTL */
        if ( ($arabic_count/$total_count) > 0.6 ) { return true; }
        return false;
    }

    /** ******************************************************************** *
     *  Basic Data Returns
     ** ******************************************************************** */
    function getLicenceList() {
        $code = array( 'ccby', 'ccbysa', 'ccbynd', 'ccbync', 'ccbyncsa', 'ccbyncnd', 'cc0' );
        $rVal = array();

        foreach ( $code as $key ) {
            $rVal[] = array( 'code'  => NoNull(strtolower($key)),
                             'label' => NoNull('license-' . strtoupper($key)),
                            );
        }

        // Return a List of License Codes
        return $rVal;
    }

    /** ******************************************************************** *
     *  File Handling Functions
     ** ******************************************************************** */
    /**
     *  Function Returns the File Extension of a Given File
     */
    function getFileExtension( $FileName ) {
        return NoNull( substr(strrchr($FileName,'.'), 1) );
    }

    /**
     *  Function Returns a Realistic Mime Type based on a File Extension
     */
    function getMimeFromExtension( $FileExt ) {
        $types = array( 'mp3' => 'audio/mp3', 'm4a' => 'audio/m4a',
                        'gif' => 'image/gif', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'tiff' => 'image/tiff', 'bmp' => 'image/bmp',
                        'mov' => 'video/quicktime', 'qt' => 'video/quicktime', 'mpg' => 'video/mpeg', 'mpeg' => 'video/mpeg', 'mp4' => 'video/mp4',
                        'pdf' => 'application/pdf',
                        'md'  => 'text/plain', 'txt' => 'text/plain', 'htm' => 'text/html', 'html' => 'text/html',
                        );
        return array_key_exists($FileExt, $types) ? NoNull($types[$FileExt]) : 'application/plain';
    }

    /**
     *  Function Determines if the DataType Being Uploaded is Valid or Not
     */
    function isValidUploadType( $FileType ) {
        $valids = array( 'audio/mp3', 'audio/mp4', 'audio/m4a', 'audio/x-mp3', 'audio/x-mp4', 'audio/mpeg', 'audio/x-m4a',
                         'image/gif', 'image/jpg', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/tiff', 'image/bmp', 'image/x-windows-bmp',
                         'video/quicktime', 'video/mpeg', 'image/x-quicktime',
                         'application/pdf', 'application/x-pdf',
                         'application/x-bzip', 'application/x-bzip2', 'application/x-compressed', 'application/x-gzip', 'multipart/x-gzip',
                         'application/plain', 'text/plain', 'text/html',
                         'application/msword', 'application/mspowerpoint', 'application/powerpoint', 'application/vnd.ms-powerpoint', 'application/x-mspowerpoint',
                         'application/vnd.ms-excel', 'application/x-excel', 'application/excel',
                         'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        );
        $rVal = false;

        // Is the FileType in the Array?
        if ( in_array($FileType, $valids) ) {
            $rVal = true;
        } else {
            writeNote( "Invalid FileType: $FileType", true );
        }

        // Return the Boolean Response
        return $rVal;
    }

    function isResizableImage( $FileType ) {
        $valids = array( 'image/jpg', 'image/jpeg', 'image/png', 'image/tiff', 'image/bmp', 'image/x-windows-bmp' );

        /* Return the Boolean Response */
        return in_array($FileType, $valids);
    }

    /** ******************************************************************** *
     *  Cache Functions
     ** ******************************************************************** *
    global $redis_db;

    /**
     *  Function determines the "correct" directory for a Cache object
     */
    function getCacheFileName( $name ) {
        if ( strlen(NoNull($name)) < 3 ) { return ''; }
        $name = strtolower($name);

        /* Do we have a series of underscores? Use a subdirectory with the file name prefix */
        if ( substr_count($name, '_') > 0 ) {
            $segs = explode('_', $name);
            $dir = NoNull($segs[0], $segs[1]);
            if ( mb_strlen($dir) >= 4 ) {
                if ( checkDIRExists(TMP_DIR . '/cache') ) {
                    if ( checkDIRExists(TMP_DIR . "/cache/$dir") ) {
                        $name = str_replace($dir . '_', $dir . '/', $name);
                    }
                }
            }
        }

        /* Return the full path and name or an empty string */
        if ( mb_strlen(NoNull($name)) >= 4 ) { return TMP_DIR . '/cache/' . $name . '.data'; }
        return '';
    }

    /**
     *  Function Records an array of information to a cache location
     */
    function setCacheObject( $keyName, $data, $expy = 0 ) {
        if ( strlen(NoNull($keyName)) < 3 ) { return false; }
        if ( defined('USE_REDIS') === false ) { define('USE_REDIS', 0); }
        $expy = nullInt($expy);

        /* Continue only if we have an array of data */
        if ( is_array($data) ) {
            /* If the data array is empty, throw in a dummy value (otherwise a serialize will not save correctly) */
            if ( count($data) <= 0 ) { $data = array( getRandomString(8) => getRandomString(64) ); }

            /* If we have Redis configured, use that. Otherwise, write to a local file */
            if ( YNBool(USE_REDIS) ) {
                /* Ensure the basics are in place with defaults */
                if ( defined('REDIS_HOST') === false ) { define('REDIS_HOST', 'localhost'); }
                if ( defined('REDIS_PASS') === false ) { define('REDIS_PASS', ''); }
                if ( defined('REDIS_PORT') === false ) { define('REDIS_PORT', 6379); }
                if ( defined('REDIS_EXPY') === false ) { define('REDIS_EXPY', 7200); }

                /* Create a connection if we do not already have one */
                if ( !$redis_db ) {
                    $redis_db = new Redis();

                    try {
                        $redis_db->connect(REDIS_HOST, REDIS_PORT);
                        if ( mb_strlen(REDIS_PASS) > 0 ) {
                            $redis_db->auth(REDIS_PASS);
                        }

                    } catch (RedisException $ex) {
                        $err = $ex->getMessage();
                        writeNote( "Could not connect to Redis: $err", true );
                    }
                }

                /* If we have a connection to Redis, check the data */
                if ( $redis_db->isConnected() ) {
                    /* Determine the key */
                    $key = str_replace(array('/', '_'), '-', $keyName);

                    /* If we have a Key Prefix, Prepend it */
                    if ( defined('REDIS_PFIX') && mb_strlen(REDIS_PFIX) >= 3 ) { $key = REDIS_PFIX . $key; }

                    /* Set the counter */
                    $GLOBALS['Perf']['redis_sets'] = nullInt($GLOBALS['Perf']['redis_sets']);
                    $GLOBALS['Perf']['redis_sets']++;

                    /* Determine the expiration time */
                    if ( $expy <= 0 ) { $expy = nullInt(REDIS_EXPY, 7200); }
                    if ( mb_substr($keyName, 0, 5) == 'token' ) { $expy = 15; }

                    /* Set the Values */
                    $redis_db->set($key, serialize($data));
                    $redis_db->expire($key, $expy);
                    return;
                }
            }

            /* If we're here, use the local cahce (including if Redis fails) */
            $cacheFile = getCacheFileName($keyName);
            if ( $cacheFile != '' && checkDIRExists( TMP_DIR . '/cache' ) ) {
                $fh = fopen($cacheFile, 'w');
                if ( is_bool($fh) === false ) {
                    /* Set the counter */
                    $GLOBALS['Perf']['cache_sets'] = nullInt($GLOBALS['Perf']['cache_sets']);
                    $GLOBALS['Perf']['cache_sets']++;

                    /* Write the data */
                    fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    fclose($fh);
                }
            }
        }
    }

    /**
     *  Function Reads cached data and returns it. If no data exists, an unhappy boolean is returned.
     */
    function getCacheObject( $keyName, $expy = 0 ) {
        if ( strlen(NoNull($keyName)) < 3 ) { return false; }
        if ( defined('USE_REDIS') === false ) { define('USE_REDIS', 0); }
        $expy = nullInt($expy);

        /* If we have Redis configured, use that. Otherwise, write to a local file */
        if ( YNBool(USE_REDIS) ) {
            /* Ensure the basics are in place with defaults */
            if ( defined('REDIS_HOST') === false ) { define('REDIS_HOST', 'localhost'); }
            if ( defined('REDIS_PASS') === false ) { define('REDIS_PASS', ''); }
            if ( defined('REDIS_PORT') === false ) { define('REDIS_PORT', 6379); }
            if ( defined('REDIS_EXPY') === false ) { define('REDIS_EXPY', 7200); }

            /* Create a connection if we do not already have one */
            if ( !$redis_db ) {
                $redis_db = new Redis();

                try {
                    $redis_db->connect(REDIS_HOST, REDIS_PORT);
                    if ( mb_strlen(REDIS_PASS) > 0 ) {
                        $redis_db->auth(REDIS_PASS);
                    }

                } catch (RedisException $ex) {
                    $err = $ex->getMessage();
                    writeNote( "Could not connect to Redis: $err", true );
                }
            }

            /* If we have a connection to Redis, check the data */
            if ( $redis_db->isConnected() ) {
                /* Determine the key */
                $key = str_replace(array('/', '_'), '-', $keyName);

                /* If we have a Key Prefix, Prepend it */
                if ( defined('REDIS_PFIX') && mb_strlen(REDIS_PFIX) >= 3 ) { $key = REDIS_PFIX . $key; }

                /* Read the Values */
                $data = $redis_db->get($key);
                if ( is_string($data) && mb_strlen($data) > 0 ) {
                    /* Set the counter */
                    $GLOBALS['Perf']['redis_gets'] = nullInt($GLOBALS['Perf']['redis_gets']);
                    $GLOBALS['Perf']['redis_gets']++;

                    /* Return the data if we have it */
                    return unserialize($data);
                }

                /* If we're here, there's nothing */
                return false;
            }
        }

        /* If we're here, use the local cache (including if Redis fails) */
        if ( checkDIRExists( TMP_DIR . '/cache' ) ) {
            $cacheFile = getCacheFileName($keyName);
            if ( file_exists( $cacheFile ) ) {
                if ( $expy <= 0 ) { $expy = nullInt(CACHE_EXPY, 7200); }
                if ( mb_substr($keyName, 0, 5) == 'token' ) { $expy = 15; }

                $age = filemtime($cacheFile);
                if ( !$age or ((time() - $age) > $expy) ) { return false; }

                $json = file_get_contents( $cacheFile );
                if  ( is_string($json) && mb_strlen($json) > 0 ) {
                    /* Set the counter */
                    $GLOBALS['Perf']['cache_gets'] = nullInt($GLOBALS['Perf']['cache_gets']);
                    $GLOBALS['Perf']['cache_gets']++;

                    /* Return the data if we have it */
                    return json_decode($json, true);
                }
            }
        }

        /* If we're here, there's nothing */
        return false;
    }

    /**
     *  Function Records any sort of ephemeral data to $GLOBALS['cache']
     */
    function setGlobalObject( $key, $data ) {
        if ( strlen(NoNull($key)) < 3 ) { return; }
        if ( is_array($GLOBALS) === false ) { return; }
        if ( array_key_exists('cache', $GLOBALS) === false ) {
            $GLOBALS['cache'] = array();
        }

        /* Set the Cache Key->Value */
        $GLOBALS['cache'][$key] = $data;
    }

    /**
     *  Function Reads any sort of ephemeral data from $GLOBALS['cache']
     */
    function getGlobalObject( $key ) {
        if ( strlen(NoNull($key)) < 3 ) { return false; }
        if ( is_array($GLOBALS) && array_key_exists('cache', $GLOBALS) ) {
            if ( array_key_exists($key, $GLOBALS['cache']) ) {
                return $GLOBALS['cache'][$key];
            }
        }

        /* Return an unhappy boolean if nothing exists */
        return false;
    }

    /** ******************************************************************** *
     *  Resource Functions
     ** ******************************************************************** */
    /**
     * Function reads a file from the file system, parses and replaces,
     *      minifies, then returns the data in a string
     */
    function readResource( $ResFile, $ReplaceList = array(), $Minify = false ) {
        $rVal = "";

        /* Check to ensure the Resource Exists */
        if ( file_exists($ResFile) ) {
            $rVal = file_get_contents( $ResFile, "r");
        }

        /* If there are Items to Replace, Do So */
        if ( count($ReplaceList) > 0 ) {
            $Search = array_keys( $ReplaceList );
            $Replace = array_values( $ReplaceList );

            /* Perform the Search/Replace Actions */
            $rVal = str_replace( $Search, $Replace, $rVal );
        }

        /* Strip all the white space if required */
        if ( $Minify ) {
            for ( $i = 0; $i < 5; $i++ ) {
                $rVal = str_replace(array("\r\n", "\r", "\n", "\t", '  '), ' ', $rVal);
            }
            $rVal = str_replace('> <', '><', $rVal);
        }

        /* Return the Data */
        return $rVal;
    }

    function getLabelValue( $String, $ReplaceList = array() ) {
        $rVal = $String;

        // If there are Items to Replace, Do So
        if ( count($ReplaceList) > 0 ) {
            $Search = array_keys( $ReplaceList );
            $Replace = array_values( $ReplaceList );

            // Perform the Search/Replace Actions
            $rVal = str_replace( $Search, $Replace, $rVal );
        }

        // Return the Appropriate String
        return $rVal;
    }

    /** ******************************************************************** *
     *  Language Functions
     ** ******************************************************************** */
    /**
     * Function returns an array containing the base language strings used within the application.
     *
     * Note: If the Language Requested does not exist, only the Application Default
     *       will be returned.
     *     : The Application Default is always loaded and values are replaced with the requested
     *       strings so long as they exist.
     */
    function getLangDefaults( $LangCd = '' ) {
        if ( defined('DEFAULT_LANG') === false ) { define('DEFAULT_LANG', 'en_US'); }
        $locale = strtolower(str_replace('_', '-', NoNull($LangCd, DEFAULT_LANG)));

        /* Collect the requested language strings */
        $data = readLangFile($locale);
        if ( is_array($data) && count($data) > 0 ) { return $data; }

        /* If we're here, there is nothing */
        return false;
    }

    /**
     *  Function reads a Language file into an array. If the language file is not the system default
     *      then it is read into the array *after* the core system language is loaded. This ensures
     *      that there is always *something* shown in the UI/API response, even if it is the wrong
     *      language.
     */
    function readLangFile( $LangCd ) {
        if ( defined('DEFAULT_LANG') === false ) { define('DEFAULT_LANG', 'en_US'); }
        $LangDefault = strtolower(str_replace('_', '-', DEFAULT_LANG));
        $LangCd = strtolower(str_replace('_', '-', $LangCd));

        $langs = array( $LangDefault );
        if ( in_array($LangCd, $langs) === false ) {
            $langs[] = $LangCd;
        }
        if ( mb_strlen($LangCd) > 2 && in_array(strtolower(mb_substr($LangCd, 0, 2)), $langs) === false ) {
            $langs[] = strtolower(mb_substr($LangCd, 0, 2));
        }
        sort($langs);

        /* Construct the Replacement and Return arrays */
        $ReplStr = array( '{version_id}' => APP_VER,
                          '{year}'       => date('Y'),
                         );
        $rVal = array();

        /* Collect the strings, overwriting where appropriate */
        foreach ( $langs as $lang ) {
            $locale = strtolower(str_replace('_', '-', $lang));

            $LangFile = LANG_DIR . "/" . $locale . ".json";
            if ( file_exists( $LangFile ) ) {
                $json = readResource( $LangFile );
                $items = json_decode($json, true);
                if ( is_array($items) ) {
                    foreach ( $items as $Key=>$Value ) {
                        $rVal["$Key"] = str_replace(array_keys($ReplStr), array_values($ReplStr), NoNull($Value));
                    }
                }
            }
        }

        /* Return the Array of Items */
        return $rVal;
    }

    /**
     *  Function determines if the provided language is valid and returns a boolean response
     */
    function isValidLanguage( $locale ) {
        $locale = strtolower(NoNull($locale));

        if ( mb_strlen($locale) >= 2 ) {
            $src = LANG_DIR . '/' . $locale . '.json';
            return file_exists($src);
        }

        /* If we're here, the requested language/locale is no good */
        return false;
    }

    /**
     * Function checks a supplied language code against the existing files located in
     *      the LANG_DIR directory, and returns an appropriate language code
     */
    function validateLanguage( $LangCd ) {
        if ( defined('DEFAULT_LANG') === false ) { define('DEFAULT_LANG', 'en-us'); }
        $lang = NoNull($LangCd, DEFAULT_LANG);

        /* If the system default language is being used, there's no point checking existing files */
        if ($lang == DEFAULT_LANG) { return $lang; }

        /* Validate the requested language file exists */
        $locale = strtolower(str_replace('_', '-', NoNull($LangCd, DEFAULT_LANG)));

        $fileName = LANG_DIR . '/' . $locale . '.json';
        if ( file_exists($fileName) ) { return $lang; }

        /* If we're here, return the default language */
        return NoNull(DEFAULT_LANG);
    }

    /** ******************************************************************** *
     *  MySQL Functions
     ** ******************************************************************** */
    global $mysql_db;
    global $pgsql_db;

    function doSQLQuery($sqlStr, $params = array(), $dbname = '') {
        if ( defined('DB_ENGINE') === false ) { define('DB_ENGINE', 'mysql'); }
        if ( defined('DB_NAME') === false ) { define('DB_NAME', ''); }

        switch ( strtolower(DB_ENGINE) ) {
            case 'pgsql':
                return doPgSQLQuery($sqlStr, $params, $dbname);
                break;

            default:
                return doMySQLQuery($sqlStr, $params, $dbname);
                break;
        }

        /* We should never be here */
        return false;
    }

    function doSQLExecute($sqlStr, $params = array(), $dbname = '') {
        if ( defined('DB_ENGINE') === false ) { define('DB_ENGINE', 'mysql'); }
        if ( defined('DB_NAME') === false ) { define('DB_NAME', ''); }

        switch ( strtolower(DB_ENGINE) ) {
            case 'pgsql':
                return doPgSQLQuery($sqlStr, $params, $dbname);
                break;

            default:
                return doMySQLExecute($sqlStr, $params, $dbname);
                break;
        }

        /* We should never be here */
        return false;
    }

    function doPgSQLQuery($sqlStr, $params = array(), $dbname = '') {
        /* Validate the Database Name */
        if ( NoNull($dbname) == '' && defined('DB_NAME') ) { $dbname = DB_NAME; }

        /* If We Have Nothing, Return Nothing */
        if ( NoNull($sqlStr) == '' ) { return false; }
        $hash = sha1($sqlStr);

        /* Check to see if this query has been run once before and, if so, return the cached result */
        $rVal = getGlobalObject($hash);
        if ( $rVal !== false ) { return $rVal; }

        $GLOBALS['Perf']['queries'] = nullInt($GLOBALS['Perf']['queries']);
        $GLOBALS['Perf']['queries']++;
        $qstart = getMicroTime();
        $result = false;

        if ( !$pgsql_db ) {
            $ReplStr = array( '[HOST]' => DB_HOST,
                              '[NAME]' => sqlScrub($dbname),
                              '[USER]' => sqlScrub(DB_USER),
                              '[PASS]' => sqlScrub(DB_PASS),
                              '[PORT]' => nullInt(DB_PORT, 5432)
                             );
            $connStr = prepSQLQuery("host=[HOST] port=[PORT] dbname=[NAME] user=[USER] password=[PASS] options='--client_encoding=UTF8'", $ReplStr);
            $pgsql_db = pg_connect($connStr);
            if ( !$pgsql_db || pg_last_error($pgsql_db) ) {
                if ( is_bool($pgsql_db) ) {
                    writeNote("doPgSQLQuery Connection Error :: -- No Connection Available --", true);
                } else {
                    writeNote("doPgSQLQuery Connection Error :: " . pg_last_error($pgsql_db), true);
                }
                return false;
            }

            /* Set the Client Encoding */
            pg_set_client_encoding($pgsql_db, "UNICODE");
        }

        /* If we have a good connection, let's go */
        if ( $pgsql_db ) {
            /* If We're In Debug, Capture the SQL Query */
            if ( defined('DEBUG_ENABLED') ) {
                if ( YNBool(DEBUG_ENABLED) ) {
                    if ( array_key_exists('debug', $GLOBALS) === false ) {
                        $GLOBALS['debug'] = array();
                        $GLOBALS['debug']['queries'] = array();
                    }
                    $didx = COUNT($GLOBALS['debug']['queries']);
                    $GLOBALS['debug']['queries'][$didx] = array( 'query' => $sqlStr,
                                                                 'time'  => 0
                                                                );
                }
            }

            $result = pg_query_params($pgsql_db, $sqlStr, $params);
        }

        /* Parse the Result If We Have One */
        if ( $result ) {
            while ($row = pg_fetch_row($result)) {
                $rr = array();
                foreach ( $row as $k=>$val ) {
                    switch ( pg_field_type($result, $k) ) {
                        case 'boolean':
                        case 'bool':
                        case 'bit':
                            $val = BoolYN(YNBool($val));
                            break;

                        case 'timestampz':
                        case 'timestamp':
                        case 'timetz':
                            $val = strtotime($val);
                            break;

                        default:
                            /* Do Nothing */
                    }
                    $rr[pg_field_name($result, $k)] = $val;
                }

                $rVal[] = $rr;
            }

            /* Clear the Result from Memory */
            pg_free_result($result);

            // Record the Ops Time (if required)
            if ( defined('DEBUG_ENABLED') ) {
                if ( DEBUG_ENABLED == 1 ) {
                    $quntil = getMicroTime();
                    $ops = round(($quntil - $qstart), 6);
                    if ( $ops < 0 ) { $ops *= -1; }

                    $GLOBALS['debug']['queries'][$didx]['time'] = $ops;
                }
            }

            /* Save the Results into Memory */
            setGlobalObject($hash, $rVal);

        } else {
            setGlobalObject('sql_last_error', pg_last_error($pgsql_db));
            writeNote("doPgSQLQuery Error :: " . pg_last_error($pgsql_db), true );
            writeNote("doPgSQLQuery Query :: $sqlStr", true );
        }

        /* Return the Array of Details */
        return $rVal;
    }

    /**
     * Function Queries the Required Database and Returns the values as an array
     */
    function doMySQLQuery($sqlStr, $params = array(), $dbname = '') {
        /* Validate the Database Name */
        if ( NoNull($dbname) == '' && defined('DB_NAME') ) { $dbname = DB_NAME; }

        /* If We Have Nothing, Return Nothing */
        if ( NoNull($sqlStr) == '' ) { return false; }
        $hash = sha1($sqlStr);

        /* Check to see if this query has been run once before and, if so, return the cached result */
        $rVal = getGlobalObject($hash);
        if ( $rVal !== false ) { return $rVal; }

        $GLOBALS['Perf']['queries'] = nullInt($GLOBALS['Perf']['queries']);
        $GLOBALS['Perf']['queries']++;
        $qstart = getMicroTime();
        $result = false;
        $rVal = array();
        $didx = 0;
        $r = 0;

        /* Do Not Proceed If We Don't Have SQL Settings */
        if ( !defined('DB_HOST') ) { return false; }

        // Determine Which Database is Required, and Connect If We Don't Already Have a Connection
        if ( !$mysql_db ) {
            $mysql_db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, $dbname);
            if ( !$mysql_db || mysqli_connect_errno() ) {
                writeNote("doMySQLQuery Connection Error :: " . mysqli_connect_error(), true);
                return false;
            }
            mysqli_set_charset($mysql_db, DB_CHARSET);
        }

        // If We Have a Good Connection, Go!
        if ( $mysql_db ) {
            /* If We're In Debug, Capture the SQL Query */
            if ( defined('DEBUG_ENABLED') ) {
                if ( DEBUG_ENABLED == 1 ) {
                    if ( array_key_exists('debug', $GLOBALS) === false ) {
                        $GLOBALS['debug'] = array();
                        $GLOBALS['debug']['queries'] = array();
                    }
                    $didx = COUNT($GLOBALS['debug']['queries']);
                    $GLOBALS['debug']['queries'][$didx] = array( 'query' => $sqlStr,
                                                                 'time'  => 0
                                                                );
                }
            }

            $result = mysqli_query($mysql_db, $sqlStr);
        }

        // Parse the Result If We Have One
        if ( $result ) {
            $finfo = mysqli_fetch_fields($result);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $arr_row = array();
                foreach ( $finfo as $col ) {
                    $arr_row[ $col->name ] = $row[$col->name];
                }
                $rVal[] = $arr_row;
            }

            // Close the MySQL Connection
            mysqli_free_result($result);

            // Record the Ops Time (if required)
            if ( defined('DEBUG_ENABLED') ) {
                if ( DEBUG_ENABLED == 1 ) {
                    $quntil = getMicroTime();
                    $ops = round(($quntil - $qstart), 6);
                    if ( $ops < 0 ) { $ops *= -1; }

                    $GLOBALS['debug']['queries'][$didx]['time'] = $ops;
                }
            }

            /* Save the Results into Memory */
            setGlobalObject($hash, $rVal);

        } else {
            writeNote("doMySQLQuery Error :: " . mysqli_errno($mysql_db) . " | " . mysqli_error($mysql_db), true );
            writeNote("doMySQLQuery Query :: $sqlStr", true );
        }

        // Return the Array of Details
        return $rVal;
    }

    /**
     * Function Executes a SQL String against the Required Database and Returns a boolean response.
     */
    function doMySQLExecute($sqlStr, $params = array(), $dbname = '') {
        $GLOBALS['Perf']['queries'] = nullInt($GLOBALS['Perf']['queries']);
        $sqlQueries = array();
        $rVal = -1;

        /* Do Not Proceed If We Don't Have SQL Settings */
        if ( !defined('DB_HOST') ) { return false; }
        if ( NoNull($dbname) == '' && defined('DB_NAME') ) { $dbname = DB_NAME; }

        /* Strip Out The SQL Queries (If There Are Many) */
        if ( strpos($sqlStr, SQL_SPLITTER) > 0 ) {
            $sqlQueries = explode(SQL_SPLITTER, $sqlStr);
        } else {
            $sqlQueries[] = $sqlStr;
        }

        /* If We Don't Already Have a Connection to the Write Server, Make One */
        if ( !$mysql_db ) {
            $mysql_db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, $dbname);
            if ( !$mysql_db || mysqli_connect_errno() ) {
                writeNote("doSQLExecute Connection Error :: " . mysqli_connect_error(), true);
                return $rVal;
            }
            mysqli_set_charset($mysql_db, DB_CHARSET);
        }

        /* Execute Each Statement */
        if ( $mysql_db ) {
            foreach ( $sqlQueries as $sqlStatement ) {
                if ( NoNull($sqlStatement) != "" ) {
                    $GLOBALS['Perf']['queries']++;
                    if ( !mysqli_query($mysql_db, $sqlStatement) ) {
                        switch ( mysqli_errno($mysql_db) ) {
                            case 1213:
                                /* Deadlock Found. Retry. */
                                if ( !mysqli_query($mysql_db, $sqlStatement) ) { break; }

                            default:
                                writeNote("doMySQLExecute Error (WriteDB) :: " . mysqli_errno($mysql_db) . " | " . mysqli_error($mysql_db), true);
                                writeNote("doMySQLExecute Query :: $sqlStatement", true);
                        }
                    }
                }
            }

            /* Get the Insert ID or the Number of Affected Rows */
            $rVal = mysqli_insert_id( $mysql_db );
            if ( $rVal == 0 ) { $rVal = mysqli_affected_rows( $mysql_db ); }
        }

        /* Return the Insert ID or an Unhappy Integer */
        return $rVal;
    }

    /**
     *  Function Closes a Persistent SQL Connection If Exists
     */
    function closePersistentSQLConn() {
        if ( $mysql_db ) { mysqli_close($mysql_db); }
        if ( $pgsql_db ) { pg_close($pgsql_db); }
    }

    /**
     *  Function Returns a Completed SQL Statement based on the SQL String and Parameter Array Provided
     */
    function prepSQLQuery($sqlStr, $ReplStr = array(), $Minify = false) {
        $rVal = str_replace(array_keys($ReplStr), array_values($ReplStr), $sqlStr);
        if ( is_bool($Minify) !== true ) { $Minify = YNBool($Minify); }

        /* Strip all the white space if required */
        if ( $Minify ) {
            for ( $i = 0; $i < 5; $i++ ) {
                $rVal = str_replace(array("\r\n", "\r", "\n", "\t", '  '), ' ', $rVal);
            }
            $rVal = str_replace('> <', '><', $rVal);
        }

        /* Return the prepped SQL Query */
        return NoNull($rVal);
    }

    /**
     * Function returns a SQL-safe String
     */
    function sqlScrub( $str ) {
        if ( NoNull($str) == '' ) { return ''; }
        $rVal = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $str);

        if( is_array($str) ) { return array_map(__METHOD__, $str); }
        if(!empty($str) && is_string($str)) {
            $ReplStr = array( '\\' => '\\\\',   "\0" => '\\0',      "\n" => "\\n",
                              "\r" => "\\n",    "\t" => '\\t',      "'" => "''",
                              '"' => '\\"',     "\x1a" => '\\Z',
                             );
            $rVal = str_replace(array_keys($ReplStr), array_values($ReplStr), $str);
        }

        // Return the Scrubbed String
        return NoNull($rVal);
    }

    /** ******************************************************************** *
     *  Alpha-Int Code Switching Functions
     ** ******************************************************************** */
    /**
     * Function returns the Character Table Required for Alpha->Int Conversions
     */
    function getChrTable() {
        return array('jNn7uY2ETd6JUOSVkAMyhCt3qw1WcpIv5P0LK4DfXFzbl8xemrB9RHGgoiQZsa',
                     '3tDL8pPwScIbnE0gsjvK2QxoVhrf17eG6yM4BJkOTXWzNduiFHZqAC9UmY5Ral',
                     'JyADsUFtkjzXqLG0SMb1egmhw8Q6cETpVfI5xdl42H9vROKYuNiWonPC73rBaZ',
                     '2ZTSUXQFPgK7nwOi0N5s8z1rjqC4E6VHkRypo3J9hdBImxAGltWeMvYfLuDbca',
                     '8NlPjJIHE7naFyewTqmdsK5YQhU9gp6WRXBVGouMDALtr0c324bzCSfOv1iZkx',
                     'OPwcLs1zy69KpNjm0hFGaEte5UIrfVBXZYQWv27S34MJHkTbdgDARlConqx8iu'
                    );
    }

    /**
     * Function converts an AlphaNumeric Value to an Integer based on the
     *      static characters passed.
     */
    function alphaToInt($alpha) {
        $chrTable = getChrTable();

        /* Perform Some Basic Error Checking */
        if (!$alpha) { return 0; }
        if ( strlen($alpha) != 6 ) { return 0; }

        $radic = strlen($chrTable[0]);
        $offset = strpos($chrTable[0], $alpha[0]);
        if ($offset === false) { return 0; }
        $value = 0;

        for ($i=1; $i < strlen($alpha); $i++) {
            if ($i >= count($chrTable)) break;

            $pos = (strpos($chrTable[$i], $alpha[$i]) + $radic - $offset) % $radic;
            if ($pos === false) { return 0; }

            $value = $value * $radic + $pos;
        }

        $value = $value * $radic + $offset;

        /* Return the Integer Value */
        return nullInt($value);
    }

    /**
     * Function converts an Integer to an AlphaNumeric Value based on the
     *      static characters passed.
     */
    function intToAlpha($num) {
        if ( nullInt( $num ) <= 0 ) { return ""; }

        $chrTable = getChrTable();
        $digit = 5;
        $radic = strlen( $chrTable[0] );
        $alpha = '';

        $num2 = floor($num / $radic);
        $mod = $num - $num2 * $radic;
        $offset = $mod;

        for ($i=0; $i<$digit; $i++) {
            $mod = $num2 % $radic;
            $num2 = ($num2 - $mod) / $radic;

            $alpha = $chrTable[ $digit-$i ][ ($mod + $offset )% $radic ] . $alpha;
        }
        $alpha = $chrTable[0][ $offset ] . $alpha;

        // Return the AlphaNumeric Value
        return $alpha;
    }

    /** ******************************************************************** *
     *  HTTP Asyncronous Calls
     ** ******************************************************************** */
    /**
     *  Function Calls a URL Asynchronously, and Returns Nothing
     *  Source: http://stackoverflow.com/questions/962915/how-do-i-make-an-asynchronous-get-request-in-php
     */
    function curlPostAsync( $url, $params ) {
        foreach ($params as $key => &$val) {
            if (is_array($val)) $val = implode(',', $val);
            $post_params[] = $key.'='.urlencode($val);
        }
        $post_string = implode('&', $post_params);
        $parts=parse_url($url);

        $fp = fsockopen($parts['host'], isset($parts['port'])?$parts['port']:80, $errno, $errstr, 30);

        $out = "POST ".$parts['path']." HTTP/1.1\r\n";
        $out.= "Host: ".$parts['host']."\r\n";
        $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out.= "Content-Length: ".strlen($post_string)."\r\n";
        $out.= "Connection: Close\r\n\r\n";
        if (isset($post_string)) $out.= $post_string;

        fwrite($fp, $out);
        fclose($fp);
    }


    /**
     *  Function Calls a URL Asynchronously, and Returns Nothing
     *  Source: http://codeissue.com/issues/i64e175d21ea182/how-to-make-asynchronous-http-calls-using-php
     */
    function httpPostAsync( $url, $paramstring, $method = 'get', $timeout = '30', $returnresponse = false ) {
        $method = strtoupper($method);
        $urlParts = parse_url($url);
        $fp = fsockopen($urlParts['host'],
                        isset( $urlParts['port'] ) ? $urlParts['port'] : 80,
                        $errno, $errstr, $timeout);
        $rVal = false;

        //If method="GET", add querystring parameters
        if ($method='GET')
            $urlParts['path'] .= '?'.$paramstring;

        $out = "$method ".$urlParts['path']." HTTP/1.1\r\n";
        $out.= "Host: ".$urlParts['host']."\r\n";
        $out.= "Connection: Close\r\n";

        //If method="POST", add post parameters in http request body
        if ($method='POST') {
            $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out.= "Content-Length: ".strlen($paramstring)."\r\n\r\n";
            $out.= $paramstring;
        }

        fwrite($fp, $out);

        //Wait for response and return back response only if $returnresponse=true
        if ( $returnresponse ) {
            $rVal = stream_get_contents($fp);
        } else {
            $rVal = true;
        }

        // Close the Connection
        fclose($fp);

        // Return the Result
        return $rVal;
    }

    /***********************************************************************
     *  Stats Recording
     ***********************************************************************/
    function recordUsageStat( $data, $http_code, $message = '' ) {
        $precision = 6;
        $GLOBALS['Perf']['app_f'] = getMicroTime();
        $App = round(( $GLOBALS['Perf']['app_f'] - $GLOBALS['Perf']['app_s'] ), $precision);
        $SqlOps = nullInt( $GLOBALS['Perf']['queries'] ) + 1;

        $Referer = str_replace($data['HomeURL'], '', urldecode(NoNull($_SERVER['HTTP_REFERER'])));
        $Agent = parse_user_agent();
        $IPv4 = getVisitorIPv4();

        $SiteId = nullInt($data['_site_id']);
        if ( $SiteId <= 1 && nullInt(getGlobalObject('siteid'), 1) > 1 ) {
            $SiteId = nullInt(getGlobalObject('siteid'));
        }

        /* Set the Values and Run the SQL Query */
        $ReplStr = array( '[SITE_ID]'    => nullInt($SiteId, 1),
                          '[TOKEN_ID]'   => nullInt($data['_token_id']),
                          '[HTTP_CODE]'  => nullInt($http_code),
                          '[REQ_TYPE]'   => sqlScrub($data['ReqType']),
                          '[REQ_URI]'    => sqlScrub($data['ReqURI']),
                          '[REFERER]'    => sqlScrub($Referer),
                          '[IP_ADDR]'    => sqlScrub($IPv4),
                          '[LOCALE]'     => sqlScrub(NoNull($data['_language_code'], $data['DispLang'])),
                          '[AGENT]'      => sqlScrub($_SERVER['HTTP_USER_AGENT']),
                          '[UAPLATFORM]' => sqlScrub($Agent['platform']),
                          '[UABROWSER]'  => sqlScrub($Agent['browser']),
                          '[UAVERSION]'  => sqlScrub($Agent['version']),
                          '[RUNTIME]'    => $App,
                          '[SQL_OPS]'    => $SqlOps,
                          '[MESSAGE]'    => sqlScrub($message),
                         );
        $sqlStr = readResource(SQL_DIR . '/system/setUsageStat.sql', $ReplStr, true);
        $isOK = doSQLExecute($sqlStr);

        if ( defined('DEBUG_ENABLED') ) {
            if ( DEBUG_ENABLED == 1 ) {
                if ( is_array($GLOBALS['debug']) ) {
                    $json = json_encode($GLOBALS['debug'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
                    writeDebug($json, 'sqlops');
                }
            }
        }

        /* Return the UsageStats.id Value */
        return $isOK;
    }

    /***********************************************************************
     *  Output Formatting Functions
     ***********************************************************************/
    /**
     *  Function formats the result in the appropriate format and returns the data
     */
    function formatResult( $data, $sets, $type = 'text/html', $status = 200, $meta = false, $more = false ) {
        $validTypes = array('application/json', 'application/octet-stream', 'application/rss+xml', 'application/xml', 'pretty/json', 'text/html');
        $nullTypes = array( 'boolean', 'integer', 'double', 'float', 'resource', 'resource (closed)', 'NULL', 'unknown type' );
        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1');
        $szOrigin = NoNull($_SERVER['HTTP_ORIGIN'], '*');
        $appType = NoNull($type, 'text/html');

        /* Ensure the Content-Type is valid for the route */
        if ( in_array($appType, $validTypes) === false ) {
            $appType = ($sets['Route'] == 'api') ? 'application/json' : 'text/html';
        }

        /* Ensure the Inputs are Complete */
        if ( is_bool($more) === false ) { $more = false; }

        /* Verify and Structure the Data */
        if ( is_object($data) ) { $data = objectToArray($data); }
        if ( in_array(gettype($data), $nullTypes) ) { $data = false; }

        /* If We Have an Array, Convert it to the Appropriate Output Format */
        if ( is_array($data) || is_bool($data) ) {
            switch ( $appType ) {
                case 'application/json':
                    /* The Text will be the most recent error */
                    $metaText = ((is_array($meta) && count($meta) > 0) ? $meta[count($meta) - 1] : false);

                    /* If there are any other errors, let's get them loaded */
                    $metaList = false;
                    if ( mb_strlen($metaText) > 0 && count($meta) > 1 ) {
                        $metaList = array();
                        foreach ( $meta as $msg ) {
                            if ( $msg != $metaText && in_array($msg, $metaList) === false ) {
                                $metaList[] = $msg;
                            }
                        }

                    }

                    /* Structure the JSON response */
                    $json = array( 'meta' => array( 'code' => $status ),
                                   'data' => $data
                                  );

                    /* Add any necessary Meta items */
                    if ( is_string($metaText) && mb_strlen($metaText) > 0 ) { $json['meta']['text'] = $metaText; }
                    if ( is_array($metaList) && count($metaList) > 0 ) { $json['meta']['more'] = $metaList; }
                    if ( BoolYN($more) === true ) { $json['meta']['more'] = YNBool($more); }

                    /* Encode the Response */
                    $data = json_encode($json, JSON_UNESCAPED_UNICODE);
                    break;

                case 'pretty/json':
                    $appType = 'application/json';
                    $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    break;

                default:
                    /* Do Nothing */
            }
        }

        /* If This is a Pre-Flight Request, Ensure the Status is Valid */
        if ( NoNull($_SERVER['REQUEST_METHOD']) == 'OPTIONS' ) { $status = 200; }

        /* Return the Data in the Requested Format */
        header($protocol . ' ' . nullInt($status) . ' ' . getHTTPCode($status) );
        header("Content-Type: " . $appType);
        header("Access-Control-Allow-Origin: $szOrigin");
        header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Credentials: true");
        header("P3P: CP=\"ALL IND DSP COR ADM CONo CUR CUSo IVAo IVDo PSA PSD TAI TELo OUR SAMo CNT COM INT NAV ONL PHY PRE PUR UNI\"");
        header("X-Perf-Stats: " . getRunTime('header'));
        header("X-SHA1-Hash: " . sha1( $data ));
        header("X-Content-Length: " . mb_strlen($data));

        /* Record the Usage Statistic */
        recordUsageStat( $sets, $status, ((is_array($meta) && count($meta) > 0) ? $meta[count($meta) - 1] : '') );

        /* Close the Persistent SQL Connection (If Needs Be) and Return */
        closePersistentSQLConn();
        exit( $data );
    }

    /**
     *  Function Sends a resource to the browser if it exists
     */
    function sendResourceFile( $srcPath, $fileName, $mimeType, $sets ) {
        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1');
        $szOrigin = NoNull($_SERVER['HTTP_ORIGIN'], '*');
        $status = 200;

        /* Determine whether the file should be downloaded or presented */
        $disposition = 'attachment';
        $group = strtok($mimeType, '/');
        if ( in_array($group, array('audio', 'image', 'video', 'text')) ) { $disposition = 'inline'; }
        if (isset($_SERVER['HTTP_RANGE'])) { $status = 206; }

        /* If the file exists, return it */
        if ( file_exists($srcPath) ) {
            $name = basename($fileName);
            $size = filesize($srcPath);
            $time = date('r', filemtime($srcPath));

            $pos = 0;
            $end = $size - 1;

            $fm = @fopen($srcPath, 'rb');
            if (!$fm) {
                header ("HTTP/1.1 505 Internal server error");
                return false;
            }

            /* Are We Continuing From a Set Location? */
            if (isset($_SERVER['HTTP_RANGE'])) {
                if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)) {
                    $pos = intval($matches[1]);
                    if (!empty($matches[2])) { $end = intval($matches[2]); }
                }
            }

            /* Set the Headers */
            header($protocol . ' ' . nullInt($status) . ' ' . getHTTPCode($status) );
            header("Access-Control-Allow-Origin: $szOrigin");
            header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
            header("Access-Control-Allow-Credentials: true");
            header("Accept-Ranges: bytes");
            header("P3P: CP=\"ALL IND DSP COR ADM CONo CUR CUSo IVAo IVDo PSA PSD TAI TELo OUR SAMo CNT COM INT NAV ONL PHY PRE PUR UNI\"");
            header("Cache-Control: public, must-revalidate, max-age=0");
            header("Content-Type: $mimeType");
            header("Content-Disposition: $disposition; filename=$name");
            header("Content-Transfer-Encoding: binary");
            header("Content-Length:" . (($end - $pos) + 1));
            header("Pragma: no-cache");
            if (isset($_SERVER['HTTP_RANGE'])) {
                header("Content-Range: bytes $pos-$end/$size");
            }
            header("Last-Modified: $time");
            header('Connection: close');

            /* Send the File From the Requested Location */
            $sent = 0;
            $cur = $pos;
            fseek($fm, $pos, 0);

            while( !feof($fm) && $cur <= $end && (connection_status() == 0) ) {
                print fread($fm, min(1024 * 16, ($end - $cur) + 1));
                $sent += min(1024 * 16, ($end - $cur) + 1);
                $cur += 1024 * 16;
                flush();
                ob_flush();
            }

            /* Close the File */
            fclose($fm);

        } else {
            return false;
        }

        /* Record the statistics and exit */
        recordUsageStat( $sets, $status );
        exit();
    }

    /**
     *  Function Sends a Zip file to the browser if it exists
     */
    function sendZipFile( $fileName ) {
        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1');
        $szOrigin = NoNull($_SERVER['HTTP_ORIGIN'], '*');
        $status = 200;

        if ( file_exists($fileName) ) {
            $name = basename($fileName);

            header($protocol . ' ' . nullInt($status) . ' ' . getHTTPCode($status) );
            header("Access-Control-Allow-Origin: $szOrigin");
            header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
            header("Access-Control-Allow-Credentials: true");
            header("P3P: CP=\"ALL IND DSP COR ADM CONo CUR CUSo IVAo IVDo PSA PSD TAI TELo OUR SAMo CNT COM INT NAV ONL PHY PRE PUR UNI\"");
            header("Content-Type: application/zip");
            header("Content-Disposition: attachment; filename=$name");
            header("Content-Length: " . filesize($fileName));
            readfile($fileName);

        } else {
            $status = 404;

            header($protocol . ' ' . nullInt($status) . ' ' . getHTTPCode($status) );
            header("Access-Control-Allow-Origin: $szOrigin");
            header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
            header("Access-Control-Allow-Credentials: true");
            header("P3P: CP=\"ALL IND DSP COR ADM CONo CUR CUSo IVAo IVDo PSA PSD TAI TELo OUR SAMo CNT COM INT NAV ONL PHY PRE PUR UNI\"");
        }

        exit();
    }

    /**
     *  Function Returns the Appropriate HTTP Reponse Code
     */
    function getHTTPCode( $code ) {
        switch ( nullInt($code) ) {
            case 100: return 'Continue'; break;
            case 101: return 'Switching Protocols'; break;
            case 200: return 'OK'; break;
            case 201: return 'Created'; break;
            case 202: return 'Accepted'; break;
            case 203: return 'Non-Authoritative Information'; break;
            case 204: return 'No Content'; break;
            case 205: return 'Reset Content'; break;
            case 206: return 'Partial Content'; break;
            case 218: return 'This Is Fine'; break;
            case 300: return 'Multiple Choices'; break;
            case 301: return 'Moved Permanently'; break;
            case 302: return 'Moved Temporarily'; break;
            case 303: return 'See Other'; break;
            case 304: return 'Not Modified'; break;
            case 305: return 'Use Proxy'; break;
            case 400: return 'Bad Request'; break;
            case 401: return 'Unauthorized'; break;
            case 402: return 'Payment Required'; break;
            case 403: return 'Forbidden'; break;
            case 404: return 'Not Found'; break;
            case 405: return 'Method Not Allowed'; break;
            case 406: return 'Not Acceptable'; break;
            case 407: return 'Proxy Authentication Required'; break;
            case 408: return 'Request Time-out'; break;
            case 409: return 'Conflict'; break;
            case 410: return 'Gone'; break;
            case 411: return 'Length Required'; break;
            case 412: return 'Precondition Failed'; break;
            case 413: return 'Request Entity Too Large'; break;
            case 414: return 'Request-URI Too Large'; break;
            case 415: return 'Unsupported Media Type'; break;
            case 420: return 'Enhance Your Calm'; break;
            case 500: return 'Internal Server Error'; break;
            case 501: return 'Not Implemented'; break;
            case 502: return 'Bad Gateway'; break;
            case 503: return 'Service Unavailable'; break;
            case 504: return 'Gateway Time-out'; break;
            case 505: return 'HTTP Version not supported'; break;
            default:
                return 'Unknown HTTP Response';
        }
    }

    /**
     *  Function Returns the Run Time and Number of SQL Queries Performed to Fulfill Request
     */
    function getRunTime( $format = 'html' ) {
        if ( defined('USE_REDIS') === false ) { define('USE_REDIS', 0); }

        $precision = 6;
        $GLOBALS['Perf']['app_f'] = getMicroTime();
        $App = round(( $GLOBALS['Perf']['app_f'] - $GLOBALS['Perf']['app_s'] ), $precision);
        $SQL = nullInt( $GLOBALS['Perf']['queries'] );

        /* If the application ran in "no time", return a zero */
        if ( $GLOBALS['Perf']['app_f'] <= ($GLOBALS['Perf']['app_s'] + 0.0001) ) { $App = 0; }

        $cache_out = '';
        if ( YNBool(USE_REDIS) ) {
            $cache_out = nullInt($GLOBALS['Perf']['redis_sets']) . ' Redis Write' . ((nullInt($GLOBALS['Perf']['redis_sets']) != 1) ? 's' : '') . ' and ' .
                         nullInt($GLOBALS['Perf']['redis_gets']) . ' Redis Read' . ((nullInt($GLOBALS['Perf']['redis_gets']) != 1) ? 's' : '');
        } else {
            $cache_out = nullInt($GLOBALS['Perf']['cache_sets']) . ' Temp Write' . ((nullInt($GLOBALS['Perf']['cache_sets']) != 1) ? 's' : '') . ' and ' .
                         nullInt($GLOBALS['Perf']['cache_gets']) . ' Temp Read' . ((nullInt($GLOBALS['Perf']['cache_gets']) != 1) ? 's' : '');
        }

        $lblSecond = ( $App == 1 ) ? "Second" : "Seconds";
        $lblQuery  = ( $SQL == 1 ) ? "Query"  : "Queries";

        // Reutrn the Run Time String
        return ($format == 'html') ? "    <!-- Page generated in roughly: $App $lblSecond, with $SQL SQL $lblQuery, $cache_out -->" : "$App $lblSecond | $SQL SQL $lblQuery | " . $cache_out;
    }

    /***********************************************************************
     *  Browser Agent Functions
     ***********************************************************************/
    function parse_user_agent() {
        $platform = null;
        $browser  = null;
        $version  = null;
        $empty = array( 'platform' => $platform, 'browser' => $browser, 'version' => $version );
        $u_agent = false;

        if( isset($_SERVER['HTTP_USER_AGENT']) ) { $u_agent = $_SERVER['HTTP_USER_AGENT']; } else { return $empty; }
        if( !$u_agent ) return $empty;

        if( preg_match('/\((.*?)\)/im', $u_agent, $parent_matches) ) {
            preg_match_all('/(?P<platform>BB\d+;|Android|CrOS|Tizen|iPhone|iPad|iPod|Linux|Macintosh|Windows(\ Phone)?|Silk|linux-gnu|BlackBerry|PlayBook|X11|(New\ )?Nintendo\ (WiiU?|3?DS)|Xbox(\ One)?)
                    (?:\ [^;]*)?
                    (?:;|$)/imx', $parent_matches[1], $result, PREG_PATTERN_ORDER);
            $priority = array( 'Xbox One', 'Xbox', 'Windows Phone', 'Tizen', 'Android', 'CrOS', 'X11' );
            $result['platform'] = array_unique($result['platform']);
            if( count($result['platform']) > 1 ) {
                if( $keys = array_intersect($priority, $result['platform']) ) {
                    $platform = reset($keys);
                } else {
                    $platform = $result['platform'][0];
                }
            } elseif( isset($result['platform'][0]) ) {
                $platform = $result['platform'][0];
            }
        }
        if( $platform == 'linux-gnu' || $platform == 'X11' ) {
            $platform = 'Linux';
        } elseif( $platform == 'CrOS' ) {
            $platform = 'Chrome OS';
        }
        preg_match_all('%(?P<browser>Camino|Kindle(\ Fire)?|Firefox|Iceweasel|Safari|MSIE|Trident|AppleWebKit|TizenBrowser|Chrome|
                    Vivaldi|IEMobile|Opera|OPR|Silk|Midori|Edge|Brave|CriOS|UCBrowser|
                    Baiduspider|Googlebot|YandexBot|bingbot|Lynx|Version|Wget|curl|
                    Valve\ Steam\ Tenfoot|
                    NintendoBrowser|PLAYSTATION\ (\d|Vita)+)
                    (?:\)?;?)
                    (?:(?:[:/ ])(?P<version>[0-9A-Z.]+)|/(?:[A-Z]*))%ix',
            $u_agent, $result, PREG_PATTERN_ORDER);
        // If nothing matched, return null (to avoid undefined index errors)
        if( !isset($result['browser'][0]) || !isset($result['version'][0]) ) {
            if( preg_match('%^(?!Mozilla)(?P<browser>[A-Z0-9\-]+)(/(?P<version>[0-9A-Z.]+))?%ix', $u_agent, $result) ) {
                return array( 'platform' => $platform ?: null, 'browser' => $result['browser'], 'version' => isset($result['version']) ? $result['version'] ?: null : null );
            }
            return $empty;
        }
        if( preg_match('/rv:(?P<version>[0-9A-Z.]+)/si', $u_agent, $rv_result) ) {
            $rv_result = $rv_result['version'];
        }
        $browser = $result['browser'][0];
        $version = $result['version'][0];
        $lowerBrowser = array_map('strtolower', $result['browser']);
        $find = function ( $search, &$key, &$value = null ) use ( $lowerBrowser ) {
            $search = (array)$search;
            foreach( $search as $val ) {
                $xkey = array_search(strtolower($val), $lowerBrowser);
                if( $xkey !== false ) {
                    $value = $val;
                    $key   = $xkey;
                    return true;
                }
            }
            return false;
        };
        $key = 0;
        $val = '';
        if( $browser == 'Iceweasel' ) {
            $browser = 'Firefox';
        } elseif( $find('Playstation Vita', $key) ) {
            $platform = 'PlayStation Vita';
            $browser  = 'Browser';
        } elseif( $find(array( 'Kindle Fire', 'Silk' ), $key, $val) ) {
            $browser  = $val == 'Silk' ? 'Silk' : 'Kindle';
            $platform = 'Kindle Fire';
            if( !($version = $result['version'][$key]) || !is_numeric($version[0]) ) {
                $version = $result['version'][array_search('Version', $result['browser'])];
            }
        } elseif( $find('NintendoBrowser', $key) || $platform == 'Nintendo 3DS' ) {
            $browser = 'NintendoBrowser';
            $version = $result['version'][$key];
        } elseif( $find('Kindle', $key, $platform) ) {
            $browser = $result['browser'][$key];
            $version = $result['version'][$key];
        } elseif( $find('OPR', $key) ) {
            $browser = 'Opera Next';
            $version = $result['version'][$key];
        } elseif( $find('Opera', $key, $browser) ) {
            $find('Version', $key);
            $version = $result['version'][$key];
        } elseif( $find(array( 'IEMobile', 'Edge', 'Midori', 'Vivaldi', 'Valve Steam Tenfoot', 'Chrome' ), $key, $browser) ) {
            $version = $result['version'][$key];
        } elseif( $browser == 'MSIE' || ($rv_result && $find('Trident', $key)) ) {
            $browser = 'MSIE';
            $version = $rv_result ?: $result['version'][$key];
        } elseif( $find('UCBrowser', $key) ) {
            $browser = 'UC Browser';
            $version = $result['version'][$key];
        } elseif( $find('CriOS', $key) ) {
            $browser = 'Chrome';
            $version = $result['version'][$key];
        } elseif( $browser == 'AppleWebKit' ) {
            if( $platform == 'Android' && !($key = 0) ) {
                $browser = 'Android Browser';
            } elseif( strpos($platform, 'BB') === 0 ) {
                $browser  = 'BlackBerry Browser';
                $platform = 'BlackBerry';
            } elseif( $platform == 'BlackBerry' || $platform == 'PlayBook' ) {
                $browser = 'BlackBerry Browser';
            } else {
                $find('Safari', $key, $browser) || $find('TizenBrowser', $key, $browser);
            }
            $find('Version', $key);
            $version = $result['version'][$key];
        } elseif( $pKey = preg_grep('/playstation \d/i', array_map('strtolower', $result['browser'])) ) {
            $pKey = reset($pKey);
            $platform = 'PlayStation ' . preg_replace('/[^\d]/i', '', $pKey);
            $browser  = 'NetFront';
        }
        return array( 'platform' => $platform ?: null, 'browser' => $browser ?: null, 'version' => $version ?: null );
    }

    /***********************************************************************
     *  Debug & Error Reporting Functions
     ***********************************************************************/
    /**
     * Function records a note to the File System when DEBUG_ENABLED > 0
     *      Note: Timezone is currently set to Asia/Tokyo, but this should
     *            be updated to follow the user's time zone.
     */
    function writeNote( $Message, $doOverride = false ) {
        if ( defined('DEBUG_ENABLED') === false ) { return; }
        if ( file_exists(LOG_DIR) === false ) { return; }

        if ( YNBool(DEBUG_ENABLED) || YNBool($doOverride) ) {
            date_default_timezone_set(TIMEZONE);
            $ima = time();
            $yW = date('yW', $ima);
            $log_file = LOG_DIR . "/debug-$yW.log";

            $fh = fopen($log_file, 'a');
            if ( is_resource($fh) ) {
                $ima_str = date("F j, Y h:i:s A", $ima );
                $stringData = "[$ima_str] | Note: $Message \n";
                fwrite($fh, $stringData);
                fclose($fh);
            }
        }
    }

    function writeDebug( $text, $prefix = 'debug' ) {
        if ( defined('DEBUG_ENABLED') === false ) { return; }
        if ( file_exists(LOG_DIR) === false ) { return; }
        if ( YNBool(DEBUG_ENABLED) ) {
            if ( defined('TIMEZONE') === false ) { define('TIMEZONE', 'UTC'); }

            date_default_timezone_set(TIMEZONE);
            $ima = time();
            $log_file = LOG_DIR . "/$prefix-$ima.log";

            $fh = fopen($log_file, 'a');
            if ( is_resource($fh) ) {
                $stringData = NoNull($text);
                fwrite($fh, $stringData);
                fclose($fh);
            }
        }
    }

    /**
     * Function formats the Error Message for {Procedure} - Error and Returns it
     */
    function formatErrorMessage( $Location, $Message ) {
        writeNote( "{$Location} - $Message", false );
        return "$Message";
    }

?>