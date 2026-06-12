<?php



date_default_timezone_set("Asia/Shanghai");



use Workerman\Lib\Timer;



define('ERTONG', 1);  

define('ERTIAO', 2);  

define('WUTONG', 3);   

define('WUTIAO', 4);   

define('BAWAN', 5);   

define('BAIBAN', 6);  

define('HONGZHONG', 7);   

define('FACAI', 8);  

define('SANTONG', 9);  

define('FREE', 10);  

define('BAIDA', 11);   

define('GAME_DOUBLE', 20);



define('DOUBLE', [

    ERTONG => [3 => 1, 4 => 3, 5 => 6],

    ERTIAO => [3 => 1, 4 => 3, 5 => 6],

    SANTONG => [3 => 2, 4 => 4, 5 => 10],

    WUTONG => [3 => 3, 4 => 5, 5 => 12],

    WUTIAO => [3 => 3, 4 => 5, 5 => 12],

    BAWAN => [3 => 5, 4 => 10, 5 => 15],

    BAIBAN => [3 => 6, 4 => 15, 5 => 30],

    HONGZHONG => [3 => 8, 4 => 20, 5 => 40],

    FACAI => [3 => 10, 4 => 25, 5 => 50],

]);



define('POSSIBLE', [ERTONG => 50, ERTIAO => 50, SANTONG => 50, WUTONG => 30, WUTIAO => 30, BAWAN => 15, BAIBAN => 7, HONGZHONG => 3, FACAI => 1, FREE => 15]);



define('FREEPOSSIBLE', [ERTONG => 50, ERTIAO => 50, SANTONG => 50, WUTONG => 30, WUTIAO => 30, BAWAN => 10, BAIBAN => 3, HONGZHONG => 2, FACAI => 1, FREE => 1]);

define('BIG_WIN', [3 => 40, 2 => 20, 1 => 10]);



define('WILD_PERCENT', 5000);



define('HORSE', [

    'double' => 20,

    'score' => 1

]);



class Table

{

    private $userInfo = [];

    private $uid = 0;

    private $free = 0;

    private $map = [];

    private $betGold = 0;

    private $betDouble = 1;

    private $roomRule = [];

    private $use = 0;

    private $mTimer = 0;

    private $mapPossible = [];

    private $wildPossible = [];

    private $mapPossibleSum = [];



    private $cur_result = [

        'disappear' => [],

        'score' => [],

    ];



    private $deal_map = [];



    private $all_score = 0;



    private $all_free = 0;



    private $free_get = 0;   



    private $save_maps = [];  



    private $distime = 0;  



    private $freedistime = 0;  



    private $next_free_count = 999;  



    private $normal_round_count = 0;
    private $free_guarantee_threshold = 0;  
    private $is_free_guarantee = false;
    private $free_guarantee_min = 135;
    private $free_guarantee_max = 225;
    private $free_total_limit_double = 30; 
    private $free_total_min_double = 0;    // 当前这轮免费最低体验倍数
    private $free_prize_level = 0;  

    private $free_boom_left_count = 0;      // 剩余第几转触发爆奖
    private $free_boom_min_double = 0;      // 爆奖转最低倍数
    private $free_boom_max_double = 0;      // 爆奖转最高倍数
    private $free_boom_used = false;        // 本轮免费是否已经爆过

    private $guarantee_bet_gold = 0;
    private $guarantee_bet_double = 0;
    private $guarantee_count = 0;

    // 控制数据缓存（静态缓存避免重复查询数据库）
    private static $controlMapCache = [];
    private static $controlMapCacheTime = [];
    private static $controlInfoCache = [];
    private static $controlInfoCacheTime = [];
    private const CACHE_TTL = 5; // 缓存5秒

    // 获取带缓存的ControlMap
    private function getCachedControlMap($uid, $gtype, $level) {
        $key = "{$uid}_{$gtype}_{$level}";
        $now = time();
        if (isset(self::$controlMapCache[$key]) && isset(self::$controlMapCacheTime[$key]) && ($now - self::$controlMapCacheTime[$key]) < self::CACHE_TTL) {
            return self::$controlMapCache[$key];
        }
        $result = DBInstance::GetControlMap($uid, $gtype, $level);
        self::$controlMapCache[$key] = $result;
        self::$controlMapCacheTime[$key] = $now;
        return $result;
    }

    // 获取带缓存的ControlInfo
    private function getCachedControlInfo($control) {
        $now = time();
        if (isset(self::$controlInfoCache[$control]) && isset(self::$controlInfoCacheTime[$control]) && ($now - self::$controlInfoCacheTime[$control]) < self::CACHE_TTL) {
            return self::$controlInfoCache[$control];
        }
        $result = DBInstance::GetControlInfo('control_mjhlt', ['level' => $control]);
        self::$controlInfoCache[$control] = $result;
        self::$controlInfoCacheTime[$control] = $now;
        return $result;
    }



    

    public function __construct($msg)

    {

        $this->roomRule = $msg;
        $this->free_guarantee_threshold = mt_rand($this->free_guarantee_min, $this->free_guarantee_max);
        /*$this->Msg_MJHLT_Start([

            'event' => 'Msg_MJHLT_Start',

            'uid' => '111111',

            'data' => [

                'score' => 10,

                'double' => 1

            ]

        ]);*/

        //$this->getResult([[8,6,2,7,8],[9,5,7,8,1],[9,6,7,9,0],[9,10,7,7,10],[10,8,10,9,7]], 1, 10);



        /*$arr = [

            'free_map' => [

                POSSIBLE,

                POSSIBLE,

                POSSIBLE,

                POSSIBLE,

                POSSIBLE,

            ],

            'free_wild' => [0, 20, 40, 50, 0],

            'map' => [

                FREEPOSSIBLE,

                FREEPOSSIBLE,

                FREEPOSSIBLE,

                FREEPOSSIBLE,

                FREEPOSSIBLE,

            ],

            'wild' => [0, 50, 100, 100 ,0],

        ];

        var_dump(json_encode($arr));*/

    }



    



    public function All_RECV($message)

    {

        switch ($message['event']) {

            case 'Msg_MJHLT_Start':

                $this->Msg_MJHLT_Start($message);

                break;

            case 'Msg_Game_Out':

                $this->Msg_Game_Out($message);

                break;

            case 'Msg_Game_RoomInfo':

                $this->Msg_Game_RoomInfo($message);

                break;

            default:

            {

                Logic::SendError($message['uid'], $message['event'], '');

                MyTools::msg('uid:' . $message['uid'] . '-----UnKnown!!!!!!!--------' . $message['event'], true);

                break;

            }

        }

    }



    private function Msg_Game_RoomInfo($message)

    {

        if ($this->uid && $this->uid == $message['uid']) {

            Logic::SendRight($this->uid, 'Msg_Game_RoomInfo', [

                'free' => $this->free,

                'all_score' => $this->all_score,

                'all_free' => $this->all_free,

                'curgrade' => $this->betGold,

                'curdouble' => $this->betDouble,

                'map' => $this->map,

                'max_multiple' => max($this->roomRule['score_arr']),

                'level' => $this->roomRule['level'],

                'score_arr' => $this->roomRule['score_arr'],

                'double_arr' => $this->roomRule['double_arr'],

                'base_arr' => [GAME_DOUBLE],

                'min_gold' => $this->roomRule['min_gold'],

                'max_gold' => $this->roomRule['max_gold'],

                'bet_min' => $this->roomRule['bet_min'] ?? 10000,

                'bet_max' => $this->roomRule['bet_max'] ?? 200000,

                'gold' => $this->userInfo['gold'],

            ]);

        } else {

            Logic::SendError($message['uid'], 'Msg_Game_RoomInfo', '无效操作');

        }

    }



    

    private function Msg_MJHLT_Start($message)

    {

        $betLevelIndex = $message['data']['score'] * $message['data']['double'];

        if ($betLevelIndex <= 0) {

            Logic::SendError($message['uid'], $message['event'], '等级错误');

            return;

        }

        if (empty(Logic::$betLevel[$betLevelIndex])) {

            $levelValues = array_values(Logic::$betLevel);

            Logic::$betLevel[$betLevelIndex] = !empty($levelValues) ? min($levelValues) : 1;

        }



        if ($this->free <= 0) {

            $use = $message['data']['score'] * $message['data']['double'] * GAME_DOUBLE;

        } else {

            $use = 0;

        }



        if ($message['uid'] != $this->uid || $this->userInfo['gold'] < $use) {

            Logic::SendError($message['uid'], $message['event'], '无效操作');

            return;

        }

        $betMin = $this->roomRule['bet_min'] ?? 10000;

        $betMax = $this->roomRule['bet_max'] ?? 200000;

        if ($this->free <= 0 && ($use < $betMin || $use > $betMax)) {

            Logic::SendError($message['uid'], $message['event'], '投注金额超出范围');

            return;

        }



        if ($this->free <= 0) {

            $this->all_free = 0;

            $this->all_score = 0;

            $this->save_maps = [];

            $new_bet_gold = $message['data']['score'];

            $new_bet_double = $message['data']['double'];

            // 投注档位变化时，重置免费保底进度
            // 防止用户低注刷保底次数，再切高注进入免费游戏
            if (
                ($this->guarantee_bet_gold > 0 || $this->guarantee_bet_double > 0) &&
                (
                    $this->guarantee_bet_gold != $new_bet_gold ||
                    $this->guarantee_bet_double != $new_bet_double
                )
            ) {
                $this->normal_round_count = 0;
                $this->free_guarantee_threshold = mt_rand($this->free_guarantee_min, $this->free_guarantee_max);
                $this->is_free_guarantee = false;
            }

            $this->guarantee_bet_gold = $new_bet_gold;

            $this->guarantee_bet_double = $new_bet_double;

            $this->betGold = $new_bet_gold;

            $this->betDouble = $new_bet_double;

            $this->use = $this->betGold * $this->betDouble * GAME_DOUBLE;

        }



        $logo_num = $this->getCachedControlMap($this->uid, $this->roomRule['gtype'], $this->roomRule['level']);

        $level_index = $this->betGold * $this->betDouble;

        $control = $logo_num ? -2 : DBInstance::GetLBControl($this->uid, $this->roomRule['gtype'], Logic::$betLevel[$level_index]);//(add)

        $control_map = $this->getCachedControlInfo($control);

        $this->mapPossible = [];

        $this->wildPossible = [];

        

        $has_free_map = $this->free > 0 && !empty($control_map[$control]['free_map']) && is_array($control_map[$control]['free_map']);

        $has_normal_map = !empty($control_map[$control]) && is_array($control_map[$control]['map']);

        $this->mapPossibleSum = [];

        // 是否屏蔽数据库FREE权重（免费游戏通过源码保底机制获得，不依赖数据库控制）
        $屏蔽FREE权重 = ($this->free <= 0);

        if ($has_free_map) {

            $this->mapPossible = $control_map[$control]['free_map'];

            $free_wild = $control_map[$control]['free_wild'] ?? null;

            for ($i = 0; $i < 5; $i++) {

                $this->wildPossible[$i] = ($i && $free_wild) ? ($free_wild[$i] ?? 0) : 0;

                $this->mapPossibleSum[$i] = array_sum($this->mapPossible[$i]);

            }

        } elseif (!$has_normal_map) {

            for ($i = 0; $i < 5; $i++) {

                $this->mapPossible[$i] = POSSIBLE;

                $this->wildPossible[$i] = mt_rand(1, 6) ? 20 : 0;

                $this->mapPossibleSum[$i] = array_sum(POSSIBLE);

            }

        } else {

            $this->mapPossible = $control_map[$control]['map'];

            $wild = $control_map[$control]['wild'] ?? null;

            for ($i = 0; $i < 5; $i++) {

                $this->wildPossible[$i] = ($i && $wild) ? ($wild[$i] ?? 0) : 0;

                $this->mapPossibleSum[$i] = array_sum($this->mapPossible[$i]);

                // 屏蔽数据库FREE权重控制（免费游戏通过源码保底机制获得）
                if ($屏蔽FREE权重 && isset($this->mapPossible[$i][FREE])) {
                    $free_weight = $this->mapPossible[$i][FREE];
                    unset($this->mapPossible[$i][FREE]);
                    $this->mapPossibleSum[$i] -= $free_weight;
                }

            }

        }



        if ($control >= 0) {

            $this->next_free_count--;

        }



        if (isset($control_map[$control]['free_cirle']) && $this->next_free_count > $control_map[$control]['free_cirle'] || $use <= 0 || $control < 0) {

            $this->next_free_count = max($control_map[$control]['free_cirle'] ?? 0, 50);

        }




        $is_free_round = ($this->free > 0 && $this->use > 0);

            if ($this->free_prize_level == 0) {
                // 普通免费备用档
                $free_max_double = 15;
                $free_reroll_min_double = 2;
            } elseif ($this->free_prize_level == 1) {
                // 大奖/超级免费：允许单次爆到130倍以内
                $free_max_double = 130;
                $free_reroll_min_double = 20;
            } else {
                // 爆奖免费：允许单次爆到200倍以内
                $free_max_double = 300;
                $free_reroll_min_double = 80;
            }

        $use_val = $this->use;
        $free_max_score = $is_free_round ? $use_val * $free_max_double : PHP_INT_MAX;
        $free_total_left_score = $is_free_round ? max(0, $use_val * $this->free_total_limit_double - $this->free_get) : PHP_INT_MAX;
        $free_reroll_min_score = $is_free_round ? $use_val * $free_reroll_min_double : 0;

        // 判断当前这转是否是本轮免费指定的爆奖转
        $is_free_boom_round = $is_free_round && !$this->free_boom_used && $this->free_boom_left_count > 0 && $this->free == $this->free_boom_left_count;
        $need_boost_free_score = $is_free_boom_round;
        $boost_min_score = 0;
        $boost_max_score = PHP_INT_MAX;

        if ($need_boost_free_score) {
            $boost_min_score = $use_val * $this->free_boom_min_double;
            $boost_max_score = $use_val * $this->free_boom_max_double;
            $boost_max_score = min($boost_max_score, $free_total_left_score);
            if ($boost_max_score < $boost_min_score) {
                $boost_min_score = $boost_max_score;
            }
        }




        // 是否已经因为超过90倍而进入“保底重摇模式”
        $need_free_reroll_guarantee = false;

        if ($need_boost_free_score) {
            if ($this->free_boom_max_double <= 90) {
                // 第一档：单次爆45-90倍，最多重摇30次
                $loop_max = 30;
            } elseif ($this->free_boom_max_double <= 130) {
                // 第二档：单次爆80-130倍，最多重摇40次
                $loop_max = 40;
            } else {
                // 第三档：单次爆130-200倍，最多重摇50次
                $loop_max = 80;
            }
        } else {
            // 普通局只摇1次，免费普通转最多10次
            $loop_max = $is_free_round ? 10 : 1;
        }
        $best_free_score = -1;
        $best_free_map = [];
        $best_free_deal_map = [];
        $best_free_result = null;
        $found_valid_free_map = false;
        $is_unset_logo = $this->all_win >= MAX_WIN_SCORE;
        $control_val = $control;
        $empty_cur_result = [
            'disappear' => [],
            'score' => [],
            'logo_info' => [],
            'cur_gold' => [],
            'double_arr' => [],
            'cur_time' => [],
            'free_logo' => 0,
            'getfree' => 0,
        ];
        for ($i = 0; $i < $loop_max; $i++) {
            


            $this->cur_result = $empty_cur_result;
            $this->map = [];
            $this->deal_map = [];

            $this->GetMap(0, $is_unset_logo, $logo_num);

            $total_score = array_sum($this->cur_result['score']);
            // 记录爆奖转重摇中分数最高的结果
            if ($need_boost_free_score && $total_score > $best_free_score) {
                $best_free_score = $total_score;
                $best_free_map = $this->map;
                $best_free_deal_map = $this->deal_map;
                $best_free_result = $this->cur_result;
            }

            // 免费游戏：超过90倍则丢弃，并开启“30~90倍保底重摇模式”
            if ($is_free_round && $total_score > $free_max_score) {
             $need_free_reroll_guarantee = true;
            continue;
            }




            if ($is_free_round && $total_score > $free_total_left_score) {
                continue;
            }
            if ($need_boost_free_score) {
                if ($boost_min_score > 0 && ($total_score < $boost_min_score || $total_score > $boost_max_score)) {
                    continue;
                }
                if ($total_score <= 0) {
                    continue;
                }
            }

            if ($total_score <= 0 || ($this->all_win + $total_score - $use < MAX_WIN_SCORE && $total_score < 250 * $use_val) || $control_val == 2) {
                $found_valid_free_map = true;
                if ($is_free_boom_round) {
                    $this->free_boom_used = true;
                }
                break;
            }




        }


        if ($need_boost_free_score && !$found_valid_free_map && $best_free_result !== null) {
            $this->map = $best_free_map;
            $this->deal_map = $best_free_deal_map;
            $this->cur_result = $best_free_result;
            if ($is_free_boom_round) {
                $this->free_boom_used = true;
            }
        }

        $final_score = array_sum($this->cur_result['score']);
        // 兜底：如果最终仍超过上限、超过累计剩余额度，或不满足补体验要求，则清空结果
        if (
            $is_free_round &&
            (
                $final_score > $free_max_score ||
                $final_score > $free_total_left_score ||
                (
                    $need_free_reroll_guarantee &&
                    $free_total_left_score >= $free_reroll_min_score &&
                    $final_score < $free_reroll_min_score
                )
            )
        ) {
            $this->cur_result['disappear'] = [];
            $this->cur_result['score'] = [];
            $this->cur_result['logo_info'] = [];
            $this->cur_result['cur_gold'] = [];
            $this->cur_result['double_arr'] = [];
            $this->cur_result['cur_time'] = [];
        }

        $reward = 0;


        if ($this->cur_result['getfree'] > 0 && $this->free <= 0) {
            $rand_free_prize = mt_rand(1, 100);

            // 本轮免费总次数，正常是12次
            $new_free_count = $this->cur_result['getfree'];

            // 爆奖转放在中间段，避免第一转就爆，也避免最后一转才爆
            // 如果免费次数不足10，则自动适配
            $boom_min_left = min(3, $new_free_count);
            $boom_max_left = min(10, $new_free_count);

            if ($boom_max_left < $boom_min_left) {
                $boom_min_left = 1;
                $boom_max_left = $new_free_count;
            }

            if ($rand_free_prize <= 90) {
                // 85% 大奖免费：整轮目标50-150倍，其中一转爆45-90倍
                $this->free_prize_level = 1;
                $this->free_total_limit_double = 150;
                $this->free_total_min_double = 50;

                $this->free_boom_left_count = mt_rand($boom_min_left, $boom_max_left);
                $this->free_boom_min_double = 45;
                $this->free_boom_max_double = 90;
                $this->free_boom_used = false;

            } elseif ($rand_free_prize <= 99) {
                // 14% 超级免费：整轮目标80-180倍，其中一转爆80-130倍
                $this->free_prize_level = 1;
                $this->free_total_limit_double = 180;
                $this->free_total_min_double = 80;

                $this->free_boom_left_count = mt_rand($boom_min_left, $boom_max_left);
                $this->free_boom_min_double = 80;
                $this->free_boom_max_double = 130;
                $this->free_boom_used = false;

            } else {
                // 1% 爆奖免费：整轮目标150-250倍，其中一转爆130-200倍
                $this->free_prize_level = 2;
                $this->free_total_limit_double = 400;
                $this->free_total_min_double = 200;

                $this->free_boom_left_count = mt_rand($boom_min_left, $boom_max_left);
                $this->free_boom_min_double = 180;
                $this->free_boom_max_double = 300;
                $this->free_boom_used = false;
            }
        }

        if ($this->free > 0) {

            $this->free--;

        }

        $this->free += $this->cur_result['getfree'];

        $this->all_free += $this->cur_result['getfree'];





        $user_win = array_sum($this->cur_result['score']);



        $this->all_win += $user_win - $use;

        $this->useGold($user_win - $use);

        Common::SyncPlatformPlayGame($this->uid, $use, $user_win, $this->roomRule['app_rid'] ?? '');

        $latest = DBInstance::GetTableWords('user_register', 'gold', ['uid' => $this->uid]);

        if ($latest && isset($latest['gold'])) {

            $this->userInfo['gold'] = $latest['gold'];

        }

        foreach (BIG_WIN as $key => $value) {

            if (intval($user_win / $this->use) >= $value) {

                $reward = $key;

                break;

            }

        }





        $this->all_score += array_sum($this->cur_result['score']);



        $data = [

            'disappear' => $this->cur_result['disappear'],

            'score' => $this->cur_result['score'],

            'logo_info' => $this->cur_result['logo_info'],

            'cur_gold' => $this->cur_result['cur_gold'],

            'cur_time' => $this->cur_result['cur_time'],

            'double_arr' => $this->cur_result['double_arr'],

            'free_logo' => $this->cur_result['free_logo'],

            'curgrade' => $this->betGold,

            'curdouble' => $this->betDouble,

            'conscore' => $use,

            'map' => $this->map,

            'free' => $this->free,

            'getfree' => $this->cur_result['getfree'],

            'reward' => $reward,

            'user_gold' => $this->userInfo['gold'],  

            'all_score' => $this->all_score,

        ];






        $record_data = [
            'score' => $this->cur_result['score'],
            'cur_gold' => $this->cur_result['cur_gold'],
            'cur_time' => $this->cur_result['cur_time'],
            'free_logo' => $this->cur_result['free_logo'],
            'curgrade' => $this->betGold,
            'curdouble' => $this->betDouble,
            'conscore' => $use,
            'free' => $this->free,
            'getfree' => $this->cur_result['getfree'],
            'reward' => $reward,
            'all_score' => $this->all_score,
        ];

        $this->save_maps[] = $record_data;



        // 只有付费普通局才参与免费保底计数
        if ($use > 0 && $this->free <= 0) {
            // 本次普通局触发了免费游戏
            if ($this->cur_result['getfree'] > 0) {
                // 重置计数和保底阈值
                $this->normal_round_count = 0;
                $this->free_guarantee_threshold = mt_rand($this->free_guarantee_min, $this->free_guarantee_max);
                $this->is_free_guarantee = false;
            } else {
                // 普通局未触发免费游戏，次数+1
                $this->normal_round_count++;

                // 达到随机阈值，标记触发保底
                if ($this->normal_round_count >= $this->free_guarantee_threshold) {
                    $this->is_free_guarantee = true;
                }

                // 没有触发免费，保持默认普通档位
                $this->free_prize_level = 0;
                $this->free_total_limit_double = 30;
                $this->free_total_min_double = 0;
            }
        }

        if ($use == 0 && $this->free <= 0) {
            $this->free_prize_level = 0;
            $this->free_total_limit_double = 30;
            $this->free_total_min_double = 0;

            $this->free_boom_left_count = 0;
            $this->free_boom_min_double = 0;
            $this->free_boom_max_double = 0;
            $this->free_boom_used = false;
        }
        
        
        Logic::SendRight($this->uid, 'Msg_MJHLT_Start', $data);

        // 结果已发送给用户，下面执行后台统计
        DBInstance::IncrementUserGet($this->uid, $user_win - $use);

        if (abs($control) != 2) {
            Logic::InsertProfit(Logic::$betLevel[$level_index], $user_win - $use);
        }

        if ($use) {

            $this->distime = count($this->map) - 1;

            $this->free_get = 0;

            $this->freedistime = 0;

        } else {

            $this->free_get += $user_win;

            $this->freedistime += count($this->map) - 1;

        }



        if ($this->free <= 0) {

            DBInstance::InsertGameRecord($this->roomRule['gtype'], $this->roomRule['level'], $this->uid, $this->use, ($this->all_score - $this->use),

                $this->distime, $this->all_free, $this->save_maps, $this->freedistime);

        }



        $double = intval($user_win / $this->use);

        if ($user_win > HORSE['score'] && $double >= HORSE['double']) {

            Logic::HorseLamp($this->uid, $user_win, $double);

        }

    }



    

    private function Msg_Game_Out($message)

    {

        Timer::del($this->mTimer);

        $gold = DBInstance::GetTableOneWord('user_register', 'gold', ['uid' => $this->uid]);

        $bank = DBInstance::GetTableOneWord('user_register', 'bank', ['uid' => $this->uid]);

        Logic::SendRight($this->uid, 'Msg_Game_Out', ['gold' => $gold, 'bank' => $bank]);

        if (!empty($this->map)) {

            $vals = ['all_score' => $this->all_score, 'all_free' => $this->all_free];

            if ($this->free) {

                $vals['distime'] = $this->distime;

                $vals['free_get'] = $this->free_get;

                $vals['save_maps'] = $this->save_maps;

                $vals['freedistime'] = $this->freedistime;

            }

            DBInstance::UpdateGameCache($this->uid, $this->roomRule['gtype'], $this->roomRule['level'], $this->map, $this->betGold, $this->betDouble, $this->use, $this->all_score, $this->free, $vals);

        }



        $olddata = [

            'rid' => $this->roomRule['rid'],

            'win' => [],

            'palyers' => [$this->uid => $this->userInfo],

            'result' => [

                'uid' => []

            ], 

            'gtype' => $this->roomRule['gtype'],

        ];

        Logic::RoomOld($olddata);

    }



    

    private function GetMap($num = 0, $control = false, $logo_num = [])

    {

        $num++;

        $free = $this->free > 0;

        $del = mt_rand(0, 1);

        $map = [];

        $free_count = [];

        $logo_count = [];

        $unset_logo = [];



        if ($logo_num) {

            $logo = max(array_keys($logo_num));


        }



        if (!empty($this->deal_map)) {

            $map = $this->deal_map;

            for ($i = 0; $i < 5; $i++) {

                $logo_count[$i] = [];

                $map[$i] = array_values($map[$i]);

                for ($j = 0; $j < 6; $j++) {

                    if (!empty($map[$i][$j])) {

                        if ($map[$i][$j] == FREE) {

                            $free_count[] = $i * 10 + $j;

                        }



                        if (!isset($logo_count[$i][$map[$i][$j]])) {

                            $logo_count[$i][$map[$i][$j]] = 1;

                        } else {

                            $logo_count[$i][$map[$i][$j]]++;

                        }

                    }

                }

            }



            foreach ($logo_count[$del] as $key1 => $value1) {

                $unset_logo[$key1] = 1;

            }

        }

        $golden_columns = [];
        $col_is_golden = [false, false, false, false, false];
        if ($this->is_free_guarantee) {
            $candidate_cols = [1, 2, 3];
            $candidate_rows = [1, 2, 3];
            shuffle($candidate_cols);
            shuffle($candidate_rows);
            for ($k = 0; $k < 3; $k++) {
                $col = $candidate_cols[$k];
                $row = $candidate_rows[$k];
                $map[$col][$row] = FREE;
                $free_count[] = $col * 10 + $row;
            }
            $this->is_free_guarantee = false;
            $this->normal_round_count = 0;
            $this->free_guarantee_threshold = mt_rand($this->free_guarantee_min, $this->free_guarantee_max);
        }

        if ($free) {
            $golden_rand = mt_rand(1, 100);
            if ($golden_rand <= 45) {
                $golden_count = 1;
            } elseif ($golden_rand <= 80) {
                $golden_count = 2;
            } else {
                $golden_count = 3;
            }
            $available_cols = [1, 2, 3];
            shuffle($available_cols);
            $golden_columns = array_slice($available_cols, 0, $golden_count);
            foreach ($golden_columns as $gc) {
                $col_is_golden[$gc] = true;
            }
        }

        $col_has_free = [false, false, false, false, false];
        for ($i = 0; $i < 5; $i++) {
            if (!empty($map[$i])) {
                foreach ($map[$i] as $cell) {
                    if ($cell == FREE) {
                        $col_has_free[$i] = true;
                        break;
                    }
                }
            }
        }

        $possible_base = [];
        for ($i = 0; $i < 5; $i++) {
            $p = $this->mapPossible[$i];
            unset($p[BAIDA]);
            $possible_base[$i] = $p;
            $possible_base_sum[$i] = array_sum($p);
        }

        for ($i = 0; $i < 5; $i++) {
            for ($j = 0; $j < 6; $j++) {
                if (!isset($map[$i][$j])) {
                    $possible = $possible_base[$i];

                    $free_cnt_val = count($free_count);
                    if ($free_cnt_val > 4
                        || (!empty($map[$i]) && $col_has_free[$i] && mt_rand(1, 100) <= 5)
                        || (($free_cnt_val >= 1 && mt_rand(1, 100) <= 90) || $free_cnt_val >= 2) && $free) {
                        unset($possible[FREE]);
                    }



                    if ($control || empty($this->map) && mt_rand(1, 100) <= 5) {

                        foreach ($possible as $key => $value) {

                            if (!isset($logo_count[$i][$key]) && isset($unset_logo[$key]) && $del != $i && $i < 3) {

                                unset($possible[$key]);

                            }

                        }

                    }



                    if (isset($logo)) {

                        unset($possible[$logo]);

                    }



                    if (empty($possible)) {

                        $possible = $possible_base[$i];

                    }



                    $sum = array_sum($possible);
                    $rand = mt_rand(1, $sum);
                    $_flag = 0;



                    foreach ($possible as $key => $value) {

                        $_flag += $value;

                        if ($rand <= $_flag) {

                            $map[$i][$j] = $key;



                            if ($i == $del && $key != BAIDA) {

                                $unset_logo[$key] = 1;

                            }



                            if ($key == FREE) {

                                $free_count[] = $i * 10 + $j;
                                $col_has_free[$i] = true;
                            }



                            if ($free && $col_is_golden[$i] && $key != FREE && $key != BAIDA) {
                                $map[$i][$j] += 100;
                            } elseif ($i && $i != 4 && $key != FREE && mt_rand(1, WILD_PERCENT) <= $this->wildPossible[$i] && $num <= 3 && !$control) {
                                $map[$i][$j] += 100;
                            }

                            break;

                        }

                    }

                }

            }



            if (!$col_has_free[$i] && empty($this->map) && $this->next_free_count <= 0 && mt_rand(1, 100) <= 80 && count($free_count) < 3) {

                $_index = mt_rand(0, 4);

                $map[$i][$_index] = FREE;

                $free_count[] = $i * 10 + $_index;
                $col_has_free[$i] = true;

            }

        }



        $double = $free ? 2 : 1;

        if ($num == 2) {

            $double *= 2;

        } elseif ($num == 3) {

            $double *= 3;

        } elseif ($num >= 4) {

            $double *= 5;

        }



        if ($num == 1 && $logo_num && isset(DOUBLE[$logo])) {

            $add_num = max($logo_num);

            for ($i = 0; $i < $add_num; $i++) {

                $_index = array_rand($map[$i]);

                $map[$i][$_index] = $logo;

            }

        }

        $this->map[] = $map;

        $this->getResult($map, $double, $num);

    }



    

    private function getResult($map, $double, $num)

    {

        $score = 0;

        $logo_count = [];

        $free_count = [];



        $logo_info = [];  

        for ($i = 0; $i < 5; $i++) {
            $logo_count[$i] = [];
            for ($j = 0; $j < 5; $j++) {
                if ($j == 4 && ($i == 0 || $i == 4)) {
                    continue;
                }
                $cell = $map[$i][$j];
                if ($cell == FREE) {
                    $free_count[] = $i * 10 + $j;
                    continue;
                }
                if ($cell == BAIDA && !$i) {
                    continue;
                }
                $logo = $cell >= 100 ? $cell % 100 : $cell;
                if (isset($logo_count[$i][$logo])) {
                    $logo_count[$i][$logo]++;
                } else {
                    $logo_count[$i][$logo] = 1;
                }
            }
        }



        $start_logo = $logo_count[0];
        $logo_get_max = [];
        $baida_counts = [];
        for ($i = 1; $i < 5; $i++) {
            $baida_counts[$i] = $logo_count[$i][BAIDA] ?? 0;
        }

        foreach ($start_logo as $key => $value) {
            $start_num = $value;
            for ($i = 1; $i < 5; $i++) {
                $self_logo = $logo_count[$i][$key] ?? 0;
                if ($self_logo > 0 || $baida_counts[$i] > 0) {
                    $start_num *= ($self_logo + $baida_counts[$i]);
                    if ($i == 4) {
                        $d = DOUBLE[$key][5];
                        $score += $start_num * $d;
                        $logo_get_max[$key] = 5;
                        $logo_info[$key] = ['tiao' => $start_num, 'lian' => 5, 'double' => $d];
                    }
                } else {
                    if ($i > 2) {
                        $d = DOUBLE[$key][$i];
                        $score += $start_num * $d;
                        $logo_get_max[$key] = $i;
                        $logo_info[$key] = ['tiao' => $start_num, 'lian' => $i, 'double' => $d];
                    }
                    break;
                }
            }
        }



        $score *= $this->betGold * $this->betDouble * $double;



        $disappear = [];
        if (!empty($logo_get_max)) {
            $max_chain = max($logo_get_max);
            for ($i = 0; $i < 5; $i++) {
                for ($j = 0; $j < 5; $j++) {
                    if ($j == 4 && ($i == 0 || $i == 4)) {
                        continue;
                    }
                    $cell = $map[$i][$j];
                    $logo = $cell >= 100 ? $cell % 100 : $cell;
                    $i_plus_1 = $i + 1;
                    if (isset($logo_get_max[$logo]) && $logo_get_max[$logo] > 1 && $logo_get_max[$logo] >= $i_plus_1) {
                        $disappear[] = $i * 10 + $j;
                        if ($cell <= BAIDA) {
                            unset($map[$i][$j]);
                        } else {
                            $map[$i][$j] = BAIDA;
                        }
                    } elseif ($cell == BAIDA && $max_chain >= $i_plus_1) {
                        $disappear[] = $i * 10 + $j;
                        unset($map[$i][$j]);
                    }
                }
            }
        }



        $this->deal_map = $map;

        $this->cur_result['disappear'][] = $disappear;

        $this->cur_result['score'][] = $score;

        $this->cur_result['logo_info'][] = $logo_info;

        $prev_score_sum = array_sum($this->cur_result['score']);
        $this->cur_result['cur_gold'][] = $this->userInfo['gold'] + $prev_score_sum;

        $this->cur_result['cur_time'][] = MyTools::GET_NOW();

        $this->cur_result['double_arr'][] = $double;


        $fcnt = count($free_count);
        if ($fcnt >= 3) {
            $this->cur_result['getfree'] = ($fcnt - 3) * 3 + 12;
            $this->cur_result['free_logo'] = $fcnt;
        }

        if (!empty($disappear) && !empty($score)) {
            $need_control = $prev_score_sum > 100 * $this->use || $double > 4 || $num > 4;
            $this->GetMap($num, $need_control);
        } else {
            if (count($this->map) == 1 && mt_rand(1, 100) <= 50) {
                for ($k = mt_rand(0, 3); $k > 0; $k--) {
                    $change_i = mt_rand(1, 3);
                    $change_j = mt_rand(0, 4);
                    $cell_val = $this->map[0][$change_i][$change_j];
                    if ($cell_val < 100 && $cell_val != BAIDA) {
                        $this->map[0][$change_i][$change_j] += 100;
                    }
                }
            }
        }

    }



    

    private function useGold($gold)

    {

        if ($gold != 0) {

            DBInstance::IncrementGolds('gold', $this->uid, $gold);

            $this->userInfo['gold'] += $gold;

        }

    }



    

    public function UserOnline($client_id, $uid)

    {

        Timer::del($this->mTimer);

        $this->Msg_Game_RoomInfo(['uid' => $uid]);

    }



    

    public function UserOff($uid)

    {

        if (!empty($this->map)) {

            $vals = ['all_score' => $this->all_score, 'all_free' => $this->all_free];

            if ($this->free) {

                $vals['distime'] = $this->distime;

                $vals['free_get'] = $this->free_get;

                $vals['save_maps'] = $this->save_maps;

                $vals['freedistime'] = $this->freedistime;

            }

            DBInstance::UpdateGameCache($this->uid, $this->roomRule['gtype'], $this->roomRule['level'], $this->map, $this->betGold, $this->betDouble, $this->use, $this->all_score, $this->free, $vals);

        }

        $this->mTimer = Timer::add(60, function () {

            $this->Msg_Game_Out(['uid' => $this->uid]);

        }, [], false);

    }



    

    public function EnterRoom($msg)

    {

        $this->uid = $msg['uid'];

        $ret = DBInstance::GetGameCache($this->uid, $this->roomRule['gtype'], $this->roomRule['level']);



        

        if (!$ret) {

            $this->betGold = min($this->roomRule['score_arr']);

            $this->betDouble = min($this->roomRule['double_arr']);

            DBInstance::InsertGameCache([

                'uid' => $this->uid,

                'gtype' => $this->roomRule['gtype'],

                'level' => $this->roomRule['level'],

                'map' => '[]',

                'bet_gold' => $this->betGold,

                'bet_double' => $this->betDouble,

                'free' => 0

            ]);
            $this->guarantee_count = 0; 

        } else {

            $this->map = $ret['map'];

            $this->free = $ret['free'];

            // 非免费状态进房，重置为默认最小下注；免费中重连，保留原下注，避免免费结算基准变化
            if ($this->free > 0) {

                $this->betGold = $ret['bet_gold'];

                $this->betDouble = $ret['bet_double'];

            } else {

                $this->betGold = min($this->roomRule['score_arr']);

                $this->betDouble = min($this->roomRule['double_arr']);

            }
            
            $this->guarantee_count = $ret['vals']['guarantee_count'] ?? 0; 

            $this->all_score = $ret['vals']['all_score'] ?? 0;

            $this->all_free = $ret['vals']['all_free'] ?? 0;

            $this->use = $this->betGold * $this->betDouble * GAME_DOUBLE;



            if ($this->free) {

                $this->distime = $ret['vals']['distime'];

                $this->free_get = $ret['vals']['free_get'];

                $this->save_maps = $ret['vals']['save_maps'];

                $this->freedistime = $ret['vals']['freedistime'];

            }

        }
        $this->map = [];   
        $this->deal_map = [];
        $this->cur_result = [
            'disappear' => [],
            'score' => [],
            'logo_info' => [],
            'cur_gold' => [],
            'double_arr' => [],
            'cur_time' => [],
            'free_logo' => 0,
            'getfree' => 0
        ];        
        if ($this->free <= 0) {
            $this->free = 0;
            $this->normal_round_count = 0;
            $this->free_guarantee_threshold = mt_rand($this->free_guarantee_min, $this->free_guarantee_max);
            $this->free_prize_level = 0;
            $this->free_total_limit_double = 30;
            $this->free_total_min_double = 0;
            $this->free_boom_left_count = 0;
            $this->free_boom_min_double = 0;
            $this->free_boom_max_double = 0;
            $this->free_boom_used = false;
        } 

        $this->userInfo = $msg;

        $this->Msg_Game_RoomInfo(['uid' => $this->uid]);

    }



    

    public function ChangeGold($msg)

    {

        $uid = $msg['uid'];

        $before = DBInstance::GetUser(['uid' => $uid]);

        DBInstance::IncrementGolds('bank', $uid, $msg['data']['num']);

        DBInstance::InsertUserProfit($uid, $msg['data']['from'], $msg['data']['type'], $msg['data']['num'], $before);

        $data = DBInstance::GetTableWords('user_register', 'bank,gold', ['uid' => $uid]);

        $this->userInfo['bank'] = $data['bank'];

        Logic::SendRight($this->uid, 'Msg_Hall_ChangeGolds', $data);

    }



    

    public function BackTransfer($message)

    {

        $ret = DBInstance::BackTransfer($message['data']['out_id']);

        $uid = $message['uid'];

        $data = $message['data'];

        $user = DBInstance::GetTableWords('user_register', 'bank,gold', ['uid' => $uid]);

        if ($ret) {

            $message['data'] = [

                'bank' => $user['bank'],

                'gold' => $user['gold'],

                'status' => 0,

            ];

            Common::SendToClient($message, $data['client_id']);



            $data = [

                'bank' => $user['bank'],

                'gold' => $user['gold'],

            ];

            $this->userInfo['bank'] = $data['bank'];

            Logic::SendRight($this->uid, 'Msg_Hall_ChangeGolds', $data);

        } else {

            $message['data'] = [

                'bank' => $user['bank'],

                'gold' => $user['gold'],

                'status' => 2,

            ];

            Common::SendToClient($message, $data['client_id']);

        }

    }



    

    public function DisRoom($msg = [])

    {

        $this->Msg_Game_Out(['uid' => $this->uid]);

    }

}

