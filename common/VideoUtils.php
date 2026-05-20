<?php

namespace common;
use support\Cache;
class VideoUtils
{
    // 通用分类体系（两级结构：大类 -> 子类）
    // 大类：ID 1-9，子类：ID = 大类ID * 100 + 序号
    private static $unifiedCategories = [
        ['type_id' => 1, 'type_name' => '电影', 'parent_id' => 0],
        ['type_id' => 101, 'type_name' => '动作片', 'parent_id' => 1],
        ['type_id' => 102, 'type_name' => '喜剧片', 'parent_id' => 1],
        ['type_id' => 103, 'type_name' => '爱情片', 'parent_id' => 1],
        ['type_id' => 104, 'type_name' => '科幻片', 'parent_id' => 1],
        ['type_id' => 105, 'type_name' => '恐怖片', 'parent_id' => 1],
        ['type_id' => 106, 'type_name' => '剧情片', 'parent_id' => 1],
        ['type_id' => 107, 'type_name' => '战争片', 'parent_id' => 1],
        ['type_id' => 2, 'type_name' => '连续剧', 'parent_id' => 0],
        ['type_id' => 201, 'type_name' => '国产剧', 'parent_id' => 2],
        ['type_id' => 202, 'type_name' => '香港剧', 'parent_id' => 2],
        ['type_id' => 203, 'type_name' => '台湾剧', 'parent_id' => 2],
        ['type_id' => 204, 'type_name' => '韩国剧', 'parent_id' => 2],
        ['type_id' => 205, 'type_name' => '日本剧', 'parent_id' => 2],
        ['type_id' => 206, 'type_name' => '欧美剧', 'parent_id' => 2],
        ['type_id' => 3, 'type_name' => '综艺', 'parent_id' => 0],
        ['type_id' => 301, 'type_name' => '大陆综艺', 'parent_id' => 3],
        ['type_id' => 302, 'type_name' => '港台综艺', 'parent_id' => 3],
        ['type_id' => 303, 'type_name' => '日韩综艺', 'parent_id' => 3],
        ['type_id' => 304, 'type_name' => '欧美综艺', 'parent_id' => 3],
        ['type_id' => 4, 'type_name' => '动漫', 'parent_id' => 0],
        ['type_id' => 401, 'type_name' => '国产动漫', 'parent_id' => 4],
        ['type_id' => 402, 'type_name' => '日韩动漫', 'parent_id' => 4],
        ['type_id' => 403, 'type_name' => '欧美动漫', 'parent_id' => 4],
        ['type_id' => 5, 'type_name' => '短剧', 'parent_id' => 0],
    ];

    // 通用分类到各渠道分类名称的映射
    private static $categoryNameMap = [
        '电影' => ['电影片', '电影'],
        '连续剧' => ['连续剧', '电视剧'],
        '综艺' => ['综艺片', '综艺'],
        '动漫' => ['动漫片', '动漫'],
        '动作片' => ['动作片'],
        '喜剧片' => ['喜剧片'],
        '爱情片' => ['爱情片'],
        '科幻片' => ['科幻片'],
        '恐怖片' => ['恐怖片'],
        '剧情片' => ['剧情片'],
        '战争片' => ['战争片'],
        '国产剧' => ['国产剧'],
        '香港剧' => ['香港剧', '港台剧'],
        '台湾剧' => ['台湾剧'],
        '韩国剧' => ['韩国剧', '韩剧'],
        '日本剧' => ['日本剧', '日剧'],
        '欧美剧' => ['欧美剧', '欧美剧'],
        '大陆综艺' => ['大陆综艺'],
        '港台综艺' => ['港台综艺'],
        '日韩综艺' => ['日韩综艺'],
        '欧美综艺' => ['欧美综艺'],
        '国产动漫' => ['国产动漫'],
        '日韩动漫' => ['日韩动漫'],
        '欧美动漫' => ['欧美动漫'],
        '短剧' => ['短剧', '爽文短剧', '短剧大全'],
    ];

    // 获取通用分类列表
    public static function getUnifiedCategories(): array
    {
        return self::$unifiedCategories;
    }

    // 获取大类（一级分类）
    public static function getParentCategories(): array
    {
        return array_values(array_filter(self::$unifiedCategories, function($cat) {
            return $cat['parent_id'] === 0;
        }));
    }

    // 获取某个大类的子类
    public static function getChildrenCategories(int $parentId): array
    {
        return array_values(array_filter(self::$unifiedCategories, function($cat) use ($parentId) {
            return $cat['parent_id'] === $parentId;
        }));
    }

    // 将通用分类ID转换为渠道实际分类ID
    public static function resolveTypeId(int $unifiedTypeId, array $channelClasses): int
    {
        $unified = self::$unifiedCategories[array_search(
            $unifiedTypeId,
            array_column(self::$unifiedCategories, 'type_id'),
            true
        )] ?? null;

        if (!$unified) return $unifiedTypeId;

        $unifiedName = $unified['type_name'];
        $aliases = self::$categoryNameMap[$unifiedName] ?? [$unifiedName];

        foreach ($channelClasses as $class) {
            $name = $class['type_name'] ?? '';
            $id = (int)($class['type_id'] ?? 0);
            if (in_array($name, $aliases, true)) {
                return $id;
            }
        }
        return 0;
    }

    // 获取带层级结构的导航数据
    public static function getHierarchicalNav(array $channelClasses): array
    {
        $parents = self::getParentCategories();
        $result = [];

        foreach ($parents as $parent) {
            $parentEntry = [
                'type_id' => $parent['type_id'],
                'type_name' => $parent['type_name'],
                'parent_id' => 0,
                'channel_type_id' => self::resolveTypeId($parent['type_id'], $channelClasses),
                'children' => [],
            ];

            $children = self::getChildrenCategories($parent['type_id']);
            foreach ($children as $child) {
                $resolvedId = self::resolveTypeId($child['type_id'], $channelClasses);
                if ($resolvedId > 0) {
                    $parentEntry['children'][] = [
                        'type_id' => $child['type_id'],
                        'type_name' => $child['type_name'],
                        'parent_id' => $child['parent_id'],
                        'channel_type_id' => $resolvedId,
                    ];
                }
            }

            // 只有大类本身有效，或者至少有一个子类有效时才加入
            if ($parentEntry['channel_type_id'] > 0 || !empty($parentEntry['children'])) {
                $result[] = $parentEntry;
            }
        }

        return $result;
    }

    // 获取大类下所有子类的 channel_type_id 列表
    public static function getChildrenTypeIds(int $unifiedParentId, array $channelClasses): array
    {
        $children = self::getChildrenCategories($unifiedParentId);
        $typeIds = [];
        foreach ($children as $child) {
            $resolvedId = self::resolveTypeId($child['type_id'], $channelClasses);
            if ($resolvedId > 0) {
                $typeIds[] = $resolvedId;
            }
        }
        return $typeIds;
    }

    private static function defaultChannelsData(): array
    {
        return [
            "code" => 1,
            "list" => [
                [
                    "channel_id" => 4,
                    "channel_name" => "无尽",
                    "channel_url" => "https://api.wujinapi.me/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "98",
                    "create_time" => "2025-01-08 17:44:09",
                    "update_time" => "2025-01-08 17:44:09"
                ],
                [
                    "channel_id" => 7,
                    "channel_name" => "360",
                    "channel_url" => "https://360zy.com/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "97",
                    "create_time" => "2025-01-15 18:05:53",
                    "update_time" => "2025-01-15 18:05:53"
                ],
                [
                    "channel_id" => 12,
                    "channel_name" => "如意",
                    "channel_url" => "https://cj.rycjapi.com/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "92",
                    "create_time" => "2025-06-25 12:56:57",
                    "update_time" => "2025-06-25 12:56:57"
                ],
                [
                    "channel_id" => 13,
                    "channel_name" => "爱祁异",
                    "channel_url" => "https://www.iqiyizyapi.com/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "92",
                    "create_time" => "2025-06-25 12:58:49",
                    "update_time" => "2025-06-25 12:58:49"
                ],
                [
                    "channel_id" => 14,
                    "channel_name" => "暴疯",
                    "channel_url" => "https://bfzyapi.com/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "92",
                    "create_time" => "2025-06-25 12:59:42",
                    "update_time" => "2025-06-25 12:59:42"
                ],
                [
                    "channel_id" => 16,
                    "channel_name" => "U酷",
                    "channel_url" => "https://api.ukuapi88.com/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "92",
                    "create_time" => "2025-06-25 13:03:51",
                    "update_time" => "2025-06-25 13:03:51"
                ],
                [
                    "channel_id" => 19,
                    "channel_name" => "电影天堂",
                    "channel_url" => "http://caiji.dyttzyapi.com/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "92",
                    "create_time" => "2025-06-26 11:48:05",
                    "update_time" => "2025-06-26 11:48:05"
                ],
                [
                    "channel_id" => 10,
                    "channel_name" => "蜂巢",
                    "channel_url" => "https://api.fczy888.me/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "86",
                    "create_time" => "2025-06-19 17:59:29",
                    "update_time" => "2025-06-19 17:59:29"
                ],
                [
                    "channel_id" => 8,
                    "channel_name" => "魔都",
                    "channel_url" => "https://www.mdzyapi.com/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "80",
                    "create_time" => "2025-06-19 17:57:34",
                    "update_time" => "2025-06-19 17:57:34"
                ],
                [
                    "channel_id" => 5,
                    "channel_name" => "木耳",
                    "channel_url" => "https://json02.heimuer.xyz/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "5",
                    "create_time" => "2025-01-08 17:47:57",
                    "update_time" => "2025-01-08 17:47:57"
                ],
                [
                    "channel_id" => 3,
                    "channel_name" => "华为",
                    "channel_url" => "https://cjhwba.com/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "3",
                    "create_time" => "2025-01-03 18:09:01",
                    "update_time" => "2025-01-03 18:09:01"
                ],
                [
                    "channel_id" => 1,
                    "channel_name" => "旺旺",
                    "channel_url" => "https://api.wwzy.tv/api.php/provide/vod/",
                    "channel_status" => "1",
                    "channel_sort" => "1",
                    "create_time" => "2025-01-03 18:05:51",
                    "update_time" => "2025-01-03 18:05:51"
                ],
            ],
            "msg" => "success"
        ];
    }

    public static function systemName(): string
    {
        return "神特么影视站";
    }
    public static function systemLogo():string
    {
        return '/favicon.ico';
    }
    public static function channels(): bool|string
    {
        $data = self::defaultChannelsData();
        if (function_exists('runtime_path')) {
            $file = runtime_path() . '/channels.json';
            if (is_file($file)) {
                $json = file_get_contents($file);
                $decoded = json_decode($json, true);
                if (is_array($decoded) && isset($decoded['list']) && is_array($decoded['list'])) {
                    $data = $decoded;
                }
            }
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    public static function getAvailableChannel(): ?array
    {
        // 先查缓存
        $cacheKey = 'useChannel';
        $cacheNavKey = 'useNav';
        $channel = Cache::get($cacheKey);
        $data = Cache::get($cacheNavKey);
        if ($channel&&$data) {
//            VideoLogUtils::info($channel,'渠道');
//            VideoLogUtils::info($data,'分类');
            return ['channel'=>$channel,'data'=>$data];
        }

        // 没缓存就去循环请求
        $channels = json_decode(self::channels(), true);

        foreach ($channels['list'] as $channel) {
            if (($channel['channel_status'] ?? '1') !== '1') {
                continue;
            }
            $url = rtrim($channel['channel_url'], '/') . '?ac=list&page=1';
            $options = [
                'http' => [
                    'method'  => 'GET',
                    'header'  => [
                        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
                        "Accept: application/json",
                        "Authorization: Bearer your_token_here"
                    ],
                    'timeout' => 10, // 超时秒数
                ]
            ];
            $context = stream_context_create($options);
            $resp = @file_get_contents($url,false,$context);

            if ($resp === false) {
                continue;
            }

            $data = json_decode($resp, true);
            if (is_array($data) && isset($data['code']) && $data['code'] == 1) {
                // 写缓存，有效期 10 分钟
                Cache::set($cacheKey, $channel, 6000);
                Cache::set($cacheNavKey, $data, 6000);
                $history = Cache::get('useChannelHistory', []);
                if (!is_array($history)) {
                    $history = [];
                }
                $entry = [
                    'channel_id' => $channel['channel_id'] ?? 0,
                    'channel_name' => $channel['channel_name'] ?? '',
                    'channel_url' => $channel['channel_url'] ?? '',
                    'used_at' => date('Y-m-d H:i:s'),
                ];
                $filtered = [];
                $seen = [];
                $list = array_merge([$entry], $history);
                foreach ($list as $item) {
                    $cid = (string)($item['channel_id'] ?? '');
                    if ($cid === '' || isset($seen[$cid])) {
                        continue;
                    }
                    $seen[$cid] = true;
                    $filtered[] = $item;
                }
                $history = $filtered;
                $history = array_slice($history, 0, 5);
                Cache::set('useChannelHistory', $history, 86400);
                return ['channel'=>$channel,'data'=>$data];
            }
        }

        return null;
    }
    public static function getNav($vodData): array
    {
        $vodArray = is_string($vodData) ? json_decode($vodData, true) : $vodData;
        $classList = $vodArray['class'] ?? [];

        // 获取层级结构数据
        $hierarchical = self::getHierarchicalNav($classList);

        // 导航栏只展示大类（一级分类）
        $navItemShow = [];
        foreach ($hierarchical as $parent) {
            $navItemShow[] = [
                'type_id' => $parent['type_id'],
                'type_name' => $parent['type_name'],
                'parent_id' => 0,
                'has_children' => !empty($parent['children']),
            ];
        }

        return [
            'navItemShow' => $navItemShow,
            'navItemMore' => [],
            'hierarchical' => $hierarchical,
        ];
    }
    public static function blacklist(): array
    {
        return ['伦理片', '限制级', '少儿不宜','伦理','限制','不宜'];
    }
    public static function getVodList(int $unifiedTid = 0, array $childTypeIds = []): ?array
    {
        $cacheKey = 'useChannel';
        $channel = Cache::get($cacheKey);
        $cacheNavKey = 'useNav';
        $vodData = Cache::get($cacheNavKey);
        $classList = $vodData['class'] ?? [];

        // 如果有子类ID（大类聚合查询）
        if (!empty($childTypeIds)) {
            return self::aggregateChildCategories($channel, $childTypeIds);
        }

        $channelTid = 0;
        if ($unifiedTid > 0) {
            $channelTid = self::resolveTypeId($unifiedTid, $classList);
        }

        $apiUrl = rtrim($channel['channel_url'], '/') . '?ac=detail';
        if ($channelTid > 0) {
            $apiUrl .= '&t=' . $channelTid;
        }

        $options = [
            'http' => [
                'method'  => 'GET',
                'header'  => [
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
                    "Accept: application/json",
                ],
                'timeout' => 15,
            ]
        ];
        $context = stream_context_create($options);
        $resp = @file_get_contents($apiUrl, false, $context);

        $data = json_decode($resp, true);
        if (is_array($data) && isset($data['code']) && $data['code'] == 1) {
            return $data;
        }
        return null;
    }

    // 聚合多个子类的数据
    private static function aggregateChildCategories(array $channel, array $typeIds): ?array
    {
        $allList = [];
        $total = 0;

        foreach ($typeIds as $typeId) {
            $apiUrl = rtrim($channel['channel_url'], '/') . '?ac=detail&t=' . $typeId . '&pg=1&limit=20';
            $options = [
                'http' => [
                    'method'  => 'GET',
                    'header'  => [
                        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
                        "Accept: application/json",
                    ],
                    'timeout' => 10,
                ]
            ];
            $context = stream_context_create($options);
            $resp = @file_get_contents($apiUrl, false, $context);

            if ($resp !== false) {
                $data = json_decode($resp, true);
                if (is_array($data) && isset($data['code']) && $data['code'] == 1) {
                    $list = $data['list'] ?? [];
                    $allList = array_merge($allList, $list);
                    $total += (int)($data['total'] ?? 0);
                }
            }
        }

        // 按时间排序（假设 vod_time_add 是时间戳）
        usort($allList, function($a, $b) {
            return ($b['vod_time_add'] ?? 0) <=> ($a['vod_time_add'] ?? 0);
        });

        return [
            'code' => 1,
            'msg' => '数据列表',
            'list' => array_slice($allList, 0, 20),
            'total' => $total,
            'page' => 1,
            'pagecount' => 1,
        ];
    }
    public static function getVodDetail($channelUrl,$ids): ?array
    {
        $url = rtrim($channelUrl, '/') . '?ac=detail&ids='.$ids;
        $options = [
            'http' => [
                'method'  => 'GET',
                'header'  => [
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
                    "Accept: application/json",
                    "Authorization: Bearer your_token_here"
                ],
                'timeout' => 10, // 超时秒数
            ]
        ];
        $context = stream_context_create($options);
        $resp = @file_get_contents($url,false,$context);

        $data = json_decode($resp, true);
        if (is_array($data) && isset($data['code']) && $data['code'] == 1) {
            VideoLogUtils::info($data['list'],'视频列表');
            return $data;
        }
        return null;
    }
}
