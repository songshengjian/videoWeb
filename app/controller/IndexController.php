<?php

namespace app\controller;

use common\VideoLogUtils;
use common\VideoUtils;
use support\Cache;
use support\Request;

class IndexController
{
    public function index(Request $request)
    {
        $channelsJson = VideoUtils::channels();
        $systemName   = VideoUtils::systemName();
        $systemLogo   = VideoUtils::systemLogo();

        // 渠道
        $channels = json_decode($channelsJson, true);

        // 可用频道 + 导航
        $info       = VideoUtils::getAvailableChannel();
        $useChannel = $info['channel'];
        $vodData    = $info['data'];
        $navData    = VideoUtils::getNav($vodData);

        /* ===== 首页推荐（新增，不影响原逻辑） ===== */
        $recommendJson = $this->mainReJson();              // 静态 JSON
        $recommendArr  = json_decode($recommendJson, true);
        $recommendList = $recommendArr['list'] ?? [];

        return view('index/index', [
            'channels'        => $channels,
            'systemName'      => $systemName,
            'systemLogo'      => $systemLogo,
            'navItemShow'     => $navData['navItemShow'],
            'navItemMore'     => $navData['navItemMore'],
            'recommendList'   => $recommendList,            // 👈 新增
        ]);
    }

    public function nav(Request $request)
    {
        $unifiedTid = (int)$request->get('tid', 0);
        $channelsJson  = VideoUtils::channels();
        $systemName  = VideoUtils::systemName();
        $systemLogo  = VideoUtils::systemLogo();
        $channels = json_decode($channelsJson, true);

        $info = VideoUtils::getAvailableChannel();
        $useChannel = $info['channel'];
        $vodData    = $info['data'];
        $classList  = $vodData['class'] ?? [];
        $navData = VideoUtils::getNav($vodData);

        // 判断是否为大类（有子类的大类ID为 1,2,3,4,5）
        $isParent = in_array($unifiedTid, [1, 2, 3, 4, 5], true);
        $childTypeIds = [];

        if ($isParent) {
            // 大类点击：获取所有子类的 channel_type_id
            $childTypeIds = VideoUtils::getChildrenTypeIds($unifiedTid, $classList);
        }

        // 查询视频列表
        $videoList = VideoUtils::getVodList($unifiedTid, $childTypeIds);
        VideoLogUtils::info($navData, 'nav:分类');

        return view('index/nav', [
            'channels' => $channels,
            'systemName' => $systemName,
            'systemLogo' => $systemLogo,
            'navItemShow' => $navData['navItemShow'],
            'navItemMore' => $navData['navItemMore'],
            'navData' => $navData,
            'videoData' => $videoList,
        ]);
    }

    public function search(Request $request)
    {
        $keyword = $request->get('keyword', '');
        $systemName = VideoUtils::systemName();
        $systemLogo = VideoUtils::systemLogo();
        $channelsJson = VideoUtils::channels(); // 获取渠道列表
        $channels = json_decode($channelsJson, true);

        return view('index/search', [
            'keyword'  => $keyword,
            'channels' => $channels['list'],
            'systemName' => $systemName,
            'systemLogo' => $systemLogo,
        ]);
    }

    // 记录搜索成功渠道
    public function searchHit(Request $request)
    {
        $channelId = (int)$request->post('channel_id', 0);
        $channelName = trim((string)$request->post('channel_name', ''));
        $channelUrl = trim((string)$request->post('channel_url', ''));
        $keyword = trim((string)$request->post('keyword', ''));

        if ($channelId <= 0 || $channelName === '' || $channelUrl === '') {
            return json(['ok' => false, 'message' => 'invalid']);
        }

        $history = Cache::get('searchSuccessChannels', []);
        if (!is_array($history)) {
            $history = [];
        }

        $entry = [
            'channel_id' => $channelId,
            'channel_name' => $channelName,
            'channel_url' => $channelUrl,
            'keyword' => $keyword,
            'used_at' => date('Y-m-d H:i:s'),
        ];

        $filtered = [];
        $seen = [];
        $list = array_merge([$entry], $history);
        foreach ($list as $item) {
            $key = ($item['channel_id'] ?? '') . '|' . ($item['keyword'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $filtered[] = $item;
        }
        $history = array_slice($filtered, 0, 10);
        Cache::set('searchSuccessChannels', $history, 86400);

        return json(['ok' => true]);
    }

    // ===== API 接口方法（供 Android 客户端使用）=====
    
    /**
     * 获取视频列表（首页）
     */
    public function getVideoList(Request $request)
    {
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 20);
        $unifiedTypeId = (int)$request->get('type_id', 0);
        
        $info = VideoUtils::getAvailableChannel();
        if (!$info) {
            return json(['code' => 0, 'msg' => '无可用视频源', 'list' => [], 'total' => 0, 'page' => $page, 'pagecount' => 0]);
        }
        
        $channel = $info['channel'];
        $vodData = $info['data'];
        $classList = $vodData['class'] ?? [];
        
        // 判断是否为大类（有子类的大类ID为 1,2,3,4,5）
        $isParent = in_array($unifiedTypeId, [1, 2, 3, 4, 5], true);
        $childTypeIds = [];

        if ($isParent) {
            $childTypeIds = VideoUtils::getChildrenTypeIds($unifiedTypeId, $classList);
        }

        $videoList = VideoUtils::getVodList($unifiedTypeId, $childTypeIds);
        
        if ($videoList) {
            return json($videoList);
        }
        
        return json(['code' => 0, 'msg' => '数据解析失败', 'list' => [], 'total' => 0, 'page' => $page, 'pagecount' => 0]);
    }
    
    /**
     * 获取视频分类列表（返回通用分类）
     */
    public function getVideoTypes(Request $request)
    {
        $info = VideoUtils::getAvailableChannel();
        if (!$info) {
            return json(['code' => 0, 'msg' => '无可用视频源', 'class' => []]);
        }
        
        $vodData = $info['data'];
        $classList = $vodData['class'] ?? [];
        
        // 返回层级结构
        $hierarchical = VideoUtils::getHierarchicalNav($classList);
        
        $flatCats = [];
        foreach ($hierarchical as $parent) {
            if ($parent['channel_type_id'] > 0) {
                $flatCats[] = [
                    'type_id' => $parent['type_id'],
                    'type_name' => $parent['type_name'],
                    'parent_id' => 0,
                ];
            }
            foreach ($parent['children'] as $child) {
                $flatCats[] = [
                    'type_id' => $child['type_id'],
                    'type_name' => $child['type_name'],
                    'parent_id' => $child['parent_id'],
                ];
            }
        }
        
        return json([
            'code' => 1,
            'msg' => 'success',
            'class' => $flatCats,
            'tree' => $hierarchical,
        ]);
    }
    
    /**
     * 搜索视频
     */
    public function searchVideos(Request $request)
    {
        $keyword = $request->get('wd', '');
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 20);
        
        if (empty($keyword)) {
            return json(['code' => 0, 'msg' => '搜索关键词不能为空', 'list' => [], 'total' => 0, 'page' => $page, 'pagecount' => 0]);
        }
        
        $info = VideoUtils::getAvailableChannel();
        if (!$info) {
            return json(['code' => 0, 'msg' => '无可用视频源', 'list' => [], 'total' => 0, 'page' => $page, 'pagecount' => 0]);
        }
        
        $channel = $info['channel'];
        $url = rtrim($channel['channel_url'], '/') . '/?ac=detail&wd=' . urlencode($keyword) . '&pg=' . $page . '&limit=' . $limit;
        
        $options = [
            'http' => [
                'method'  => 'GET',
                'header'  => [
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
                    "Accept: application/json",
                ],
                'timeout' => 30,
            ]
        ];
        $context = stream_context_create($options);
        $resp = @file_get_contents($url, false, $context);
        
        if ($resp === false) {
            return json(['code' => 0, 'msg' => '搜索失败', 'list' => [], 'total' => 0, 'page' => $page, 'pagecount' => 0]);
        }
        
        $data = json_decode($resp, true);
        if (is_array($data) && isset($data['code']) && $data['code'] == 1) {
            return json($data);
        }
        
        return json(['code' => 0, 'msg' => '搜索结果解析失败', 'list' => [], 'total' => 0, 'page' => $page, 'pagecount' => 0]);
    }
    
    /**
     * 获取视频详情
     */
    public function getVideoDetail(Request $request)
    {
        $ids = $request->get('ids', '');
        
        if (empty($ids)) {
            return json(['code' => 0, 'msg' => '视频 ID 不能为空', 'list' => []]);
        }
        
        $info = VideoUtils::getAvailableChannel();
        if (!$info) {
            return json(['code' => 0, 'msg' => '无可用视频源', 'list' => []]);
        }
        
        $channel = $info['channel'];
        $url = rtrim($channel['channel_url'], '/') . '/?ac=detail&ids=' . $ids;
        
        $options = [
            'http' => [
                'method'  => 'GET',
                'header'  => [
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
                    "Accept: application/json",
                ],
                'timeout' => 30,
            ]
        ];
        $context = stream_context_create($options);
        $resp = @file_get_contents($url, false, $context);
        
        if ($resp === false) {
            return json(['code' => 0, 'msg' => '获取视频详情失败', 'list' => []]);
        }
        
        $data = json_decode($resp, true);
        if (is_array($data) && isset($data['code']) && $data['code'] == 1) {
            return json($data);
        }
        
        return json(['code' => 0, 'msg' => '视频详情解析失败', 'list' => []]);
    }
    
    /**
     * 用户登录
     */
    public function userLogin(Request $request)
    {
        $username = $request->post('user_name', '');
        $password = $request->post('user_pwd', '');
        
        if (empty($username) || empty($password)) {
            return json(['code' => 0, 'msg' => '用户名和密码不能为空']);
        }
        
        // TODO: 实际项目中应该连接数据库验证
        // 这里暂时返回成功（演示用）
        return json([
            'code' => 1,
            'msg' => '登录成功',
            'data' => [
                'user_id' => 1,
                'user_name' => $username,
                'token' => bin2hex(random_bytes(32))
            ]
        ]);
    }
    
    /**
     * 用户注册
     */
    public function userRegister(Request $request)
    {
        $username = $request->post('user_name', '');
        $password = $request->post('user_pwd', '');
        $email = $request->post('user_email', '');
        
        if (empty($username) || empty($password)) {
            return json(['code' => 0, 'msg' => '用户名和密码不能为空']);
        }
        
        if (strlen($username) < 3) {
            return json(['code' => 0, 'msg' => '用户名至少 3 个字符']);
        }
        
        if (strlen($password) < 6) {
            return json(['code' => 0, 'msg' => '密码至少 6 个字符']);
        }
        
        // TODO: 实际项目中应该连接数据库保存用户
        // 这里暂时返回成功（演示用）
        return json([
            'code' => 1,
            'msg' => '注册成功',
            'data' => [
                'user_id' => 1,
                'user_name' => $username,
                'token' => bin2hex(random_bytes(32))
            ]
        ]);
    }

    function proxy(Request $request)
    {
        $url = $request->get('url');
        VideoLogUtils::info('请求的url:' . $url, '输出请求的渠道url');
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
        $resp = @file_get_contents($url, false, $context);
        return response($resp, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * 读取 runtime 目录下的 ads.json 并返回
     */
    public function getAds(Request $request)
    {
        $adsFile = runtime_path() . '/ads.json';
        $ads = file_exists($adsFile)
            ? json_decode(file_get_contents($adsFile), true)
            : [];

        // 兼容旧结构：字符串 => 新结构
        $defaults = [
            'top' => ['enabled' => true, 'content' => '', 'width' => 0, 'height' => 90],
            'bottom' => ['enabled' => true, 'content' => '', 'width' => 0, 'height' => 90],
            'left' => ['enabled' => true, 'content' => '', 'width' => 120, 'height' => 260],
            'right' => ['enabled' => true, 'content' => '', 'width' => 120, 'height' => 260],
            'video_top' => ['enabled' => true, 'content' => '', 'width' => 0, 'height' => 80],
            'video_bottom' => ['enabled' => true, 'content' => '', 'width' => 0, 'height' => 120],
        ];

        foreach ($defaults as $key => $def) {
            if (!isset($ads[$key])) {
                $ads[$key] = $def;
                continue;
            }
            if (is_string($ads[$key])) {
                $ads[$key] = array_merge($def, [
                    'content' => htmlspecialchars_decode($ads[$key]),
                    'enabled' => trim($ads[$key]) !== '',
                ]);
            } elseif (is_array($ads[$key])) {
                if (isset($ads[$key]['content'])) {
                    $ads[$key]['content'] = htmlspecialchars_decode($ads[$key]['content']);
                } elseif (isset($ads[$key]['html'])) {
                    $ads[$key]['content'] = htmlspecialchars_decode($ads[$key]['html']);
                    unset($ads[$key]['html']);
                }
                $ads[$key] = array_merge($def, $ads[$key]);
            } else {
                $ads[$key] = $def;
            }
        }

        return json($ads);
    }

    /**
     * 播放器顶部广告位配置（由 IndexController 统一控制）
     * 修改 enabled 可开关广告位；修改 html 可替换为你的广告代码（图片链接、百度/Google 等脚本）。
     * @return array {enabled: bool, html: string}
     */
    private function getPlayerAdConfig(): array
    {
        return [
            'enabled' => true,   // 是否展示广告位，改为 false 可关闭
            'html'    => '',     // 广告 HTML：可放 <a><img></a>、或百度/Google 等第三方广告脚本。为空时视图显示占位
            // 示例：'html' => '<a href="https://example.com" target="_blank" rel="noopener"><img src="/static/ad.png" alt="广告"></a>',
        ];
    }

    /**
     * 修改后的video方法 - 支持直接接收视频数据
     */
    public function video(Request $request)
    {
        $systemName = VideoUtils::systemName();
        $systemLogo = VideoUtils::systemLogo();
        // 优先从POST参数获取完整视频数据
        $videoDataJson = $request->post('videoData');
        $channelName = $request->post('channelName', '');
        $channelUrl = $request->post('channelUrl', '');

        // 修复日志调用 - 将数组作为第一个参数，字符串作为第二个参数
        VideoLogUtils::info([
            'message' => '视频页面请求接收',
            'hasPostData' => !empty($videoDataJson),
            'channelName' => $channelName,
            'channelUrl' => $channelUrl
        ], 'video_request');

        if ($videoDataJson) {
            // 直接使用传递的视频数据，无需重新请求
            $videoInfo = json_decode($videoDataJson, true);
            VideoLogUtils::info([
                'message' => '直接使用传递的视频数据',
                'vod_name' => $videoInfo['vod_name'] ?? '未知'
            ], 'video_parse');
            
            // 解析播放源数据
            $playSources = $this->parsePlaySources($videoInfo);
            
            // 构建完整的视频数据结构
            $completeVideoData = [
                'list' => [$videoInfo],
                'play_sources' => $playSources,
                'channel_name' => $channelName,
                'channel_url' => $channelUrl
            ];
            
            VideoLogUtils::info([
                'message' => '解析后的播放源数量',
                'count' => count($playSources)
            ], 'play_source_parse');
            
            return view('index/video', [
                'videoData' => $completeVideoData,
                'playerAd' => $this->getPlayerAdConfig(),
                'systemName' => $systemName,
                'systemLogo' => $systemLogo,
            ]);
        } else {
            // 兼容原有的GET参数方式
            $ids = $request->get('ids') ?: $request->get('vod_id');
            $channel_url = $request->get('channel_url');

            if (!$ids || !$channel_url) {
                VideoLogUtils::warning([
                    'message' => '缺少必要参数',
                    'ids' => $ids,
                    'channel_url' => $channel_url
                ], 'video_params_missing');
                return view('index/video', [
                    'videoData' => null,
                    'playerAd' => $this->getPlayerAdConfig(),
                    'systemName' => $systemName,
                    'systemLogo' => $systemLogo,
                ]);
            }

            // 原有逻辑：重新请求数据
            $videoData = VideoUtils::getVodDetail($channel_url, $ids);
            if (!$videoData || !isset($videoData['list'][0])) {
                VideoLogUtils::warning([
                    'message' => '获取视频详情失败',
                    'response' => $videoData
                ], 'video_detail_failed');
                return view('index/video', [
                    'videoData' => null,
                    'playerAd' => $this->getPlayerAdConfig(),
                    'systemName' => $systemName,
                    'systemLogo' => $systemLogo,
                ]);
            }

            $videoInfo = $videoData['list'][0];
            VideoLogUtils::info([
                'message' => '通过API重新获取视频数据',
                'vod_name' => $videoInfo['vod_name'] ?? '未知'
            ], 'video_api_fetch');
            
            // 解析播放源数据
            $playSources = $this->parsePlaySources($videoInfo);
            
            // 构建完整的视频数据结构
            $completeVideoData = [
                'list' => [$videoInfo],
                'play_sources' => $playSources,
                'channel_name' => '',
                'channel_url' => $channel_url
            ];
            
            return view('index/video', [
                'videoData' => $completeVideoData,
                'playerAd' => $this->getPlayerAdConfig(),
                'systemName' => $systemName,
                'systemLogo' => $systemLogo,
            ]);
        }
    }
    // public function video()
    // {
    //     return view('index/video');
    // }
    
    /**
     * 优化后的播放源解析方法 - 只保留M3U8格式的播放源
     */
    private function parsePlaySources($video)
    {
        $playSources = [];

        if (empty($video['vod_play_from']) || empty($video['vod_play_url'])) {
            VideoLogUtils::warning([
                'message' => '视频播放源数据为空'
            ], 'parse_play_sources');
            return $playSources;
        }

        // 拆分播放来源和播放链接
        $froms = explode('$$$', $video['vod_play_from']);
        $urls = explode('$$$', $video['vod_play_url']);

        $count = min(count($froms), count($urls));
        VideoLogUtils::info([
            'message' => '解析播放源数量',
            'count' => $count
        ], 'parse_play_sources');

        for ($i = 0; $i < $count; $i++) {
            $sourceName = trim($froms[$i]);
            $urlString = trim($urls[$i]);

            if (empty($sourceName) || empty($urlString)) {
                continue;
            }

            // **关键新增：检查播放源是否为M3U8格式**
            $episodeParts = explode('#', $urlString);
            $isM3U8Source = false;

            if (!empty($episodeParts)) {
                $firstPart = trim($episodeParts[0]);
                if (strpos($firstPart, '$') !== false) {
                    $firstEpisodeData = explode('$', $firstPart, 2);
                    if (count($firstEpisodeData) >= 2) {
                        $firstUrl = strtolower(trim($firstEpisodeData[1]));
                        // 判断是否包含m3u8
                        $isM3U8Source = (strpos($firstUrl, '.m3u8') !== false || strpos($firstUrl, 'm3u8') !== false);
                    }
                }
            }

            // **过滤掉非M3U8的播放源**
            if (!$isM3U8Source) {
                VideoLogUtils::info([
                    'message' => '过滤非M3U8播放源',
                    'sourceName' => $sourceName
                ], 'parse_play_sources');
                continue; // 跳过这个播放源，不解析其剧集
            }

            $episodes = [];
            // 按 # 分割剧集
            foreach ($episodeParts as $partIndex => $part) {
                $part = trim($part);
                if (empty($part)) {
                    continue;
                }

                // 按 $ 分割剧集名称和URL
                if (strpos($part, '$') !== false) {
                    $episodeData = explode('$', $part, 2);
                    if (count($episodeData) >= 2) {
                        $episodeName = trim($episodeData[0]);
                        $episodeUrl = trim($episodeData[1]);

                        if (!empty($episodeName) && !empty($episodeUrl)) {
                            $episodes[] = [
                                'name' => $episodeName,
                                'url' => $episodeUrl
                            ];
                        }
                    }
                }
            }

            if (!empty($episodes)) {
                $playSources[] = [
                    'name' => $sourceName,
                    'episodes' => $episodes
                ];
            }
        }

        VideoLogUtils::info([
            'message' => '播放源解析完成',
            'play_sources_count' => count($playSources)
        ], 'parse_play_sources');
        return $playSources;
    }
    /**
     * 修复后的视频代理方法 - 用于处理M3U8和TS文件的代理请求
     */
    /**
     * 修复M3U8相对路径问题的vproxy方法
     */
    public function vproxy(Request $request)
    {
        $url = $request->get('url');
        $debug = (string)$request->get('debug', '');
        $noRange = $request->get('norange') === '1';
        $uaMode = (string)$request->get('ua', '');
        $rewriteM3u8 = $request->get('rewrite') === '1';
        VideoLogUtils::info('原始URL参数: ' . $url, 'videoProxy');

        if (empty($url)) {
            VideoLogUtils::warning('URL参数为空', 'videoProxy');
            return response('URL参数不能为空', 400);
        }

        // 解码URL
        $targetUrl = urldecode($url);
        VideoLogUtils::info('解码后的目标URL: ' . $targetUrl, 'videoProxy');

        // 处理嵌套代理URL的情况
        if (strpos($targetUrl, '/index/vproxy') !== false) {
            parse_str(parse_url($targetUrl, PHP_URL_QUERY), $queryParams);
            $targetUrl = urldecode($queryParams['url'] ?? '');
            VideoLogUtils::info('提取嵌套URL: ' . $targetUrl, 'videoProxy');
        }

        // 验证URL格式
        if (empty($targetUrl) || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            VideoLogUtils::warning('无效的URL: ' . $targetUrl, 'videoProxy');
            return response('无效的URL格式', 400);
        }
        $targetPath = parse_url($targetUrl, PHP_URL_PATH) ?? '';
        $isTsRequest = stripos($targetPath, '.ts') !== false;

        // 提取域名进行白名单检查
        $parsedUrl = parse_url($targetUrl);
        $domain = $parsedUrl['host'] ?? '';

        if (empty($domain)) {
            VideoLogUtils::warning('无法解析域名: ' . $targetUrl, 'videoProxy');
            return response('无法解析域名', 400);
        }

        // 扩展白名单
        //        $allowedDomains = [
        //            'vip.ffzy-plays.com',
        //            'vip.ffzy-video.com',
        //            'v2.qrssuv.com',
        //            'vip.ffzyapi.com',
        //            'ffzy-plays.com',
        //            'ffzy-video.com',
        //            'qrssuv.com',
        //            'ffzy-online5.com',
        //            'svipsvip.ffzy-online5.com',
        //            'ffzy-online.com',
        //            'ffzy-online1.com',
        //            'ffzy-online2.com',
        //            'ffzy-online3.com',
        //            'ffzy-online4.com',
        //            'ffzy-online6.com',
        //            'ffzy-online7.com',
        //            'ffzy-online8.com',
        //            'ffzy-online9.com',
        //        ];
        //
        //        // 检查域名白名单
        //        $isAllowed = false;
        //        foreach ($allowedDomains as $allowed) {
        //            if (strpos($domain, $allowed) !== false || $domain === $allowed) {
        //                $isAllowed = true;
        //                break;
        //            }
        //        }
        $isAllowed = true;
        if (!$isAllowed) {
            VideoLogUtils::warning('域名不在白名单: ' . $domain, 'videoProxy');
            return response('域名不在白名单中: ' . $domain, 403);
        }

        try {
            $rangeHeader = $request->header('range');
            if ($noRange) {
                $rangeHeader = null;
            }
            $origin = ($parsedUrl['scheme'] ?? 'https') . '://' . $domain;
            $referer = $targetUrl;
            $clientUa = (string)$request->header('user-agent', '');
            $forceAndroid = $uaMode === 'android';
            $forceOkhttp = $uaMode === 'okhttp';
            $androidUa = 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
            $okhttpUa = 'okhttp/4.9.3';

            if ($uaMode === '' && $isTsRequest) {
                // TS 分片对机房/浏览器更敏感，默认走 okhttp + noRange
                $forceOkhttp = true;
                $noRange = true;
            }

            $doRequest = function (string $ua, ?string $rangeOverride = null, string $mode = '') use ($targetUrl, $origin, $referer) {
                $secChMobile = stripos($ua, 'Mobile') !== false ? '?1' : '?0';
                $secChPlatform = stripos($ua, 'Android') !== false ? '"Android"' : '"Windows"';
                if ($mode === 'okhttp') {
                    $requestHeaders = [
                        'Accept: */*',
                        'Connection: Keep-Alive',
                    ];
                } else {
                    $requestHeaders = [
                        'Accept: */*',
                        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
                        'Accept-Encoding: identity',
                        'Referer: ' . $referer,
                        'Origin: ' . $origin,
                        'Connection: keep-alive',
                        'Cache-Control: no-cache',
                        'sec-ch-ua: "Chromium";v="120", "Not=A?Brand";v="24", "Google Chrome";v="120"',
                        'sec-ch-ua-mobile: ' . $secChMobile,
                        'sec-ch-ua-platform: ' . $secChPlatform,
                        'sec-fetch-dest: video',
                        'sec-fetch-mode: no-cors',
                        'sec-fetch-site: same-site',
                        'DNT: 1',
                        'Upgrade-Insecure-Requests: 1'
                    ];
                }
                if (!empty($rangeOverride)) {
                    $requestHeaders[] = 'Range: ' . $rangeOverride;
                }

                $responseHeaders = [];
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $targetUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_TIMEOUT => 45,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_USERAGENT => $ua,
                    CURLOPT_HTTPHEADER => $requestHeaders,
                    CURLOPT_HEADER => false,
                    CURLOPT_NOBODY => false,
                    CURLOPT_PROXY => '',
                    CURLOPT_NOPROXY => '*',
                    CURLOPT_COOKIEFILE => '',
                    CURLOPT_COOKIEJAR => '',
                    CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) use (&$responseHeaders) {
                        $len = strlen($headerLine);
                        if (strpos($headerLine, ':') !== false) {
                            [$key, $value] = explode(':', $headerLine, 2);
                            $responseHeaders[trim($key)] = trim($value);
                        }
                        return $len;
                    },
                ]);
                if (strpos($targetUrl, 'https://') === 0) {
                    curl_setopt_array($ch, [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                    ]);
                }
                $response = curl_exec($ch);
                if ($response === false) {
                    $error = curl_error($ch);
                    $errorCode = curl_errno($ch);
                    $info = curl_getinfo($ch);
                    curl_close($ch);
                    return [0, '', [], "cURL错误 [{$errorCode}]: {$error}", $info, $requestHeaders, $ua];
                }
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $info = curl_getinfo($ch);
                curl_close($ch);
                return [$httpCode, $response, $responseHeaders, null, $info, $requestHeaders, $ua];
            };

            if ($forceOkhttp) {
                $ua = $okhttpUa;
                $uaModeUsed = 'okhttp';
            } elseif ($forceAndroid) {
                $ua = $androidUa;
                $uaModeUsed = 'android';
            } else {
                $ua = $clientUa ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';
                $uaModeUsed = '';
            }
            [$httpCode, $body, $responseHeaders, $err, $curlInfo, $requestHeaders, $usedUa] = $doRequest($ua, $rangeHeader, $uaModeUsed);

            if ($httpCode >= 400 && !$forceAndroid && !$forceOkhttp) {
                // 直连失败时自动降级为 Android UA 再试一次
                [$httpCode, $body, $responseHeaders, $err, $curlInfo, $requestHeaders, $usedUa] = $doRequest($androidUa, $rangeHeader, 'android');
                $forceAndroid = true;
            }

            if ($httpCode >= 500 && !$noRange && !empty($rangeHeader)) {
                // Range 可能触发源站限制，500 时退回无 Range 再试一次
                [$httpCode, $body, $responseHeaders, $err, $curlInfo, $requestHeaders, $usedUa] = $doRequest($usedUa, null, $uaModeUsed);
            }

            if ($httpCode >= 500 && !$forceOkhttp) {
                // 仍然失败时，改用 okhttp UA + 无 Range 再试一次
                $forceOkhttp = true;
                $noRange = true;
                $uaModeUsed = 'okhttp';
                [$httpCode, $body, $responseHeaders, $err, $curlInfo, $requestHeaders, $usedUa] = $doRequest($okhttpUa, null, $uaModeUsed);
            }

            if ($httpCode === 0) {
                VideoLogUtils::warning($err, 'videoProxy');
            if ($debug === '1') {
                $resp = [
                    'target_url' => $targetUrl,
                    'http_code' => 0,
                    'error' => $err,
                    'used_ua' => $usedUa,
                    'force_android' => $forceAndroid,
                    'force_okhttp' => $forceOkhttp,
                    'range' => $rangeHeader,
                    'norange' => $noRange,
                    'ua_mode' => $uaMode,
                    'ua_mode_used' => $uaModeUsed,
                    'rewrite_m3u8' => $rewriteM3u8,
                    'origin' => $origin,
                    'referer' => $referer,
                    'request_headers' => $requestHeaders,
                    'curl_info' => $curlInfo,
                    'resolved_ip' => gethostbyname($domain),
                    ];
                    return response(json_encode($resp, JSON_UNESCAPED_UNICODE), 200, [
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Cache-Control' => 'no-cache',
                    ]);
                }
                return response("请求失败: {$err}", 500);
            }

            if ($httpCode >= 400) {
                VideoLogUtils::warning("HTTP错误: {$httpCode}", 'videoProxy');
                return response("远程服务器错误: HTTP {$httpCode}", $httpCode >= 500 ? 502 : $httpCode);
            }

            if (empty($body)) {
                VideoLogUtils::warning('响应内容为空', 'videoProxy');
                return response('响应内容为空', 204);
            }

            // 确定内容类型
            $contentType = 'application/octet-stream';
            if (isset($responseHeaders['Content-Type'])) {
                $contentType = $responseHeaders['Content-Type'];
            } else {
                $path = parse_url($targetUrl, PHP_URL_PATH);
                if (strpos($path, '.m3u8') !== false) {
                    $contentType = 'application/vnd.apple.mpegurl';
                } elseif (strpos($path, '.ts') !== false) {
                    $contentType = 'video/mp2t';
                } elseif (strpos($path, '.mp4') !== false) {
                    $contentType = 'video/mp4';
                }
            }

            // 上游可能返回错误的 Content-Type，但内容是 M3U8
            $trimmedBody = ltrim($body);
            $isM3U8 = (strpos($trimmedBody, '#EXTM3U') === 0) || strpos($targetUrl, '.m3u8') !== false;
            if ($isM3U8) {
                $contentType = 'application/vnd.apple.mpegurl; charset=utf-8';
            }

            $bodyModified = false;
            // **关键修复：处理M3U8文件中的相对路径**
            if ($isM3U8 && $rewriteM3u8) {
                $body = $this->processM3U8Content($body, $targetUrl, $forceAndroid, $forceOkhttp, $noRange);
                $bodyModified = true;
                VideoLogUtils::info('已处理M3U8文件中的相对路径', 'videoProxy');
            }

            if ($debug === '1') {
                $resp = [
                    'target_url' => $targetUrl,
                    'http_code' => $httpCode,
                    'used_ua' => $usedUa,
                    'force_android' => $forceAndroid,
                    'force_okhttp' => $forceOkhttp,
                    'range' => $rangeHeader,
                    'norange' => $noRange,
                    'ua_mode' => $uaMode,
                    'ua_mode_used' => $uaModeUsed,
                    'rewrite_m3u8' => $rewriteM3u8,
                    'origin' => $origin,
                    'referer' => $referer,
                    'request_headers' => $requestHeaders,
                    'curl_info' => $curlInfo,
                    'resolved_ip' => gethostbyname($domain),
                    'content_type' => $contentType,
                    'content_length' => strlen($body),
                    'response_headers' => $responseHeaders,
                    'is_m3u8' => $isM3U8,
                    'body_prefix' => $isM3U8 ? substr($body, 0, 200) : null,
                ];
                return response(json_encode($resp, JSON_UNESCAPED_UNICODE), 200, [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cache-Control' => 'no-cache',
                ]);
            }

            $headers = [
                'Content-Type' => $contentType,
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Range',
                'Access-Control-Expose-Headers' => 'Content-Length, Content-Range',
                'Cache-Control' => $isM3U8 ? 'no-cache' : 'public, max-age=3600',
                'Pragma' => $isM3U8 ? 'no-cache' : 'cache',
                'Content-Disposition' => 'inline',
            ];

            if (isset($responseHeaders['Content-Range'])) {
                $headers['Content-Range'] = $responseHeaders['Content-Range'];
                $headers['Accept-Ranges'] = 'bytes';
            }
            if (!$bodyModified && !$isM3U8 && isset($responseHeaders['Content-Length'])) {
                $headers['Content-Length'] = $responseHeaders['Content-Length'];
            }

            VideoLogUtils::info("代理成功: {$targetUrl} | Content-Type: {$contentType} | 大小: " . strlen($body), 'videoProxy');

            return response($body, $httpCode, $headers);
        } catch (\Exception $e) {
            VideoLogUtils::warning('代理异常: ' . $e->getMessage(), 'videoProxy');
            return response('代理请求异常: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 处理M3U8文件内容，将相对路径转换为代理路径
     */
    private function processM3U8Content($content, $baseUrl, $forceAndroid = false, $forceOkhttp = false, $noRange = false)
    {
        if (empty($content)) {
            return $content;
        }

        // 获取基础URL信息
        $parsedBase = parse_url($baseUrl);
        $baseScheme = $parsedBase['scheme'] ?? 'https';
        $baseHost = $parsedBase['host'] ?? '';
        $basePath = dirname($parsedBase['path'] ?? '/');

        // 构建基础URL前缀
        $baseUrlPrefix = $baseScheme . '://' . $baseHost;
        if ($basePath !== '/' && $basePath !== '.') {
            $baseUrlPrefix .= $basePath;
        }

        VideoLogUtils::info('M3U8基础URL: ' . $baseUrlPrefix, 'processM3U8');

        $lines = explode("\n", $content);
        $processedLines = [];

        $uaParam = $forceAndroid ? '&ua=android' : ($forceOkhttp ? '&ua=okhttp' : '');
        $rangeParam = $noRange ? '&norange=1' : '';

        foreach ($lines as $line) {
            $line = trim($line);

            // 跳过空行和注释行
            if (empty($line) || strpos($line, '#') === 0) {
                // 处理含有URI的标签行，例如 EXT-X-KEY / EXT-X-MAP / EXT-X-I-FRAME-STREAM-INF
                if (strpos($line, '#EXT-X-KEY') === 0 || strpos($line, '#EXT-X-MAP') === 0 || strpos($line, '#EXT-X-I-FRAME-STREAM-INF') === 0) {
                    $line = preg_replace_callback('/URI=\"([^\"]+)\"/', function ($matches) use ($baseScheme, $baseHost, $baseUrlPrefix) {
                        $uri = $matches[1];
                        if (strpos($uri, '/index/vproxy') !== false) {
                            return 'URI="' . $uri . '"';
                        }
                        if (preg_match('/^https?:\/\//', $uri)) {
                            $fullUrl = $uri;
                        } elseif (strpos($uri, '/') === 0) {
                            $fullUrl = $baseScheme . '://' . $baseHost . $uri;
                        } else {
                            $fullUrl = $baseUrlPrefix . '/' . ltrim($uri, '/');
                        }
                        $proxyUrl = '/index/vproxy?url=' . urlencode($fullUrl);
                        return 'URI="' . $proxyUrl . '"';
                    }, $line);
                }
                $processedLines[] = $line;
                continue;
            }

            // TS 直连：不走代理，确保是绝对 URL
            if (preg_match('/\.ts(\?|$)/i', $line)) {
                if (!preg_match('/^https?:\/\//', $line)) {
                    if (strpos($line, '/') === 0) {
                        $line = $baseScheme . '://' . $baseHost . $line;
                    } else {
                        $line = $baseUrlPrefix . '/' . ltrim($line, '/');
                    }
                }
                $processedLines[] = $line;
                continue;
            }

            // 如果是相对路径，转换为通过代理的绝对路径
            if (!preg_match('/^https?:\/\//', $line)) {
                // 构建完整的远程URL
                if (strpos($line, '/') === 0) {
                    // 绝对路径（相对于域名根目录）
                    $fullUrl = $baseScheme . '://' . $baseHost . $line;
                } else {
                    // 相对路径（相对于当前目录）
                    $fullUrl = $baseUrlPrefix . '/' . ltrim($line, '/');
                }

                // 转换为代理URL
                $proxyUrl = '/index/vproxy?url=' . urlencode($fullUrl) . $uaParam . $rangeParam;
                $processedLines[] = $proxyUrl;

                VideoLogUtils::info("转换路径: {$line} -> {$proxyUrl}", 'processM3U8');
            } else {
                // 已经是绝对URL，也通过代理
                $proxyUrl = '/index/vproxy?url=' . urlencode($line) . $uaParam . $rangeParam;
                $processedLines[] = $proxyUrl;

                VideoLogUtils::info("代理绝对路径: {$line} -> {$proxyUrl}", 'processM3U8');
            }
        }

        $processedContent = implode("\n", $processedLines);
        VideoLogUtils::info('M3U8处理完成，原始行数: ' . count($lines) . '，处理后行数: ' . count($processedLines), 'processM3U8');

        return $processedContent;
    }

    /**
     * 处理OPTIONS预检请求
     */
    public function videoProxyOptions(Request $request): Response
    {
        return response('', 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Access-Control-Max-Age' => '86400'
        ]);
    }

    /**
     * 测试代理连接的调试方法
     */
    public function testProxy(Request $request)
    {
        $url = $request->get('url', 'https://svipsvip.ffzy-online5.com/20240815/31404_16e28998/index.m3u8');

        echo "<h2>代理连接测试</h2>";
        echo "<p><strong>测试URL:</strong> {$url}</p>";

        // 基本连接测试
        echo "<h3>1. 基本连接测试</h3>";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true, // 只获取头部
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            echo "<p style='color: red;'><strong>连接错误:</strong> {$error}</p>";
        } else {
            echo "<p style='color: green;'><strong>HTTP状态码:</strong> {$httpCode}</p>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }

        // 完整内容测试
        echo "<h3>2. 完整内容测试</h3>";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Referer: http://127.0.0.1:8787/',
            ],
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($error) {
            echo "<p style='color: red;'><strong>获取内容错误:</strong> {$error}</p>";
        } else {
            echo "<p><strong>HTTP状态码:</strong> {$httpCode}</p>";
            echo "<p><strong>内容类型:</strong> {$contentType}</p>";
            echo "<p><strong>内容长度:</strong> " . strlen($content) . " 字节</p>";

            if (strlen($content) > 0 && strlen($content) < 2000) {
                echo "<h4>内容预览:</h4>";
                echo "<pre>" . htmlspecialchars($content) . "</pre>";
            }
        }

        // 域名解析测试
        echo "<h3>3. 域名解析测试</h3>";
        $domain = parse_url($url, PHP_URL_HOST);
        $ip = gethostbyname($domain);
        echo "<p><strong>域名:</strong> {$domain}</p>";
        echo "<p><strong>解析IP:</strong> {$ip}</p>";

        if ($ip === $domain) {
            echo "<p style='color: red;'>域名解析失败！</p>";
        } else {
            echo "<p style='color: green;'>域名解析成功！</p>";
        }

        // PHP配置检查
        echo "<h3>4. PHP配置检查</h3>";
        echo "<p><strong>cURL支持:</strong> " . (extension_loaded('curl') ? '✓ 支持' : '✗ 不支持') . "</p>";
        echo "<p><strong>OpenSSL支持:</strong> " . (extension_loaded('openssl') ? '✓ 支持' : '✗ 不支持') . "</p>";
        echo "<p><strong>allow_url_fopen:</strong> " . (ini_get('allow_url_fopen') ? '✓ 启用' : '✗ 禁用') . "</p>";
        echo "<p><strong>max_execution_time:</strong> " . ini_get('max_execution_time') . " 秒</p>";
        echo "<p><strong>memory_limit:</strong> " . ini_get('memory_limit') . "</p>";

        return response('', 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * 增强的错误日志方法
     */
    private function logProxyError($message, $context = [])
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context,
            'server_info' => [
                'php_version' => PHP_VERSION,
                'curl_version' => curl_version()['version'] ?? 'N/A',
                'openssl_version' => OPENSSL_VERSION_TEXT ?? 'N/A',
            ]
        ];

        VideoLogUtils::warning('代理错误详情: ' . json_encode($logData, JSON_UNESCAPED_UNICODE), 'videoProxy');
    }

    /**
     * 直接硬编码的推荐
     * @return string
     */
    function mainReJson()
    {
        return '{"msg":"首页推荐","list":[{"type_name":"热播电影","vlist":[{"vod_name":"陈翔六点半之重楼别","vod_pic":"http://pic0.iqiyipic.com/image/20210915/46/32/v_132039120_m_601_m11_579_772.jpg","web":"http://xhsj.xyz/","vod_remarks":"HD"},{"vod_name":"河妖","vod_pic":"http://pic6.iqiyipic.com/image/20210922/a7/b0/v_139455855_m_601_m19_579_772.jpg","vod_remarks":"HD"},{"vod_name":"我是霸王龙","vod_pic":"https://img.ffzy888.com/upload/vod/20230111-1/0f7ce7b99a0d91de429892897fe5e285.jpg","vod_remarks":"HD"},{"vod_name":"明日战记","vod_pic":"https://img2.doubanio.com/view/photo/s_ratio_poster/public/p2876734663.webp","vod_remarks":"HD"},{"vod_name":"大哥别闹了","vod_pic":"http://pic5.iqiyipic.com/image/20220316/8e/cf/v_166003981_m_601_m4_579_772.jpg","vod_remarks":"HD"},{"vod_name":"这个杀手不太冷静","vod_pic":"https://img9.doubanio.com/view/photo/s_ratio_poster/public/p2814949620.webp","vod_remarks":"HD"}]},{"type_name":"热播连续剧","vlist":[{"vod_name":"问题住宅","vod_pic":"https://img.ffzy888.com/upload/vod/20250116-1/1935445955645f763e3829d88e9c9a8c.jpg","vod_remarks":"更新至02集"},{"vod_name":"漫长的季节","vod_pic":"https://puui.qpic.cn/vcover_vt_pic/0/mzc00200fhhxx8d1681374664538/0","vod_remarks":"已完结"},{"vod_name":"繁花","vod_pic":"https://vcover-vt-pic.puui.qpic.cn/vcover_vt_pic/0/mzc00200whsp9r61703381240331/0","vod_remarks":"已完结"},{"vod_name":"菜鸟老警第七季","vod_pic":"https://www.mdzypic.com/upload/vod/20250110-2/95bb3df948c9e2bd3131963cf969a3aa.jpg","vod_remarks":"更新至02集"},{"vod_name":"相思令","vod_pic":"https://img.ffzy888.com/upload/vod/20250120-1/7fbee19e54fe169f09dd8cdd8d3723af.jpg","vod_remarks":"更新至第19集"},{"vod_name":"鹊刀门传奇第二季","vod_pic":"https://img.ffzy888.com/upload/vod/20250122-1/9a110e460a2f0a778018a21e5e166d7e.jpg","vod_remarks":"更新至第18集"}]},{"type_name":"热播综艺","vlist":[{"vod_name":"团建不能停","vod_pic":"https://img.ffzy888.com/upload/vod/20241121-1/028f29c26ad543b1ef229570af1b5732.jpg","vod_remarks":"更新至20250127期"},{"vod_name":"爱人说","vod_pic":"https://www.mdzypic.com/upload/vod/20241220-12/e5ac14e7cb3b2886c807b943618b4478.jpg","vod_remarks":"更新至20250109期"},{"vod_name":"单排喜剧大赛","vod_pic":"https://www.mdzypic.com/upload/vod/20241227-16/3688058a5f6710b0b8dbb6aece179a8d.jpg","vod_remarks":"更新至7期"},{"vod_name":"斗笑社第三季","vod_pic":"https://img.ffzy888.com/upload/vod/20250117-1/7f6cc370738633441a74a2c6b1ee9b86.jpg","vod_remarks":"更新至20250126期"},{"vod_name":"有你的恋歌","vod_pic":"https://img.ffzy888.com/upload/vod/20250108-1/ea8c11caa074066e2f2243f82abfbbbc.jpg","vod_remarks":"更新至20250123期"},{"vod_name":"声声乐尔","vod_pic":"https://www.mdzypic.com/upload/vod/20241219-11/ad2d66d15a67a4e4d6931d73432342f2.jpg","vod_remarks":"更新至20241218期"}]},{"type_name":"热播动漫","vlist":[{"vod_name":"虽然我是注定没落的贵族闲来无事只好来深究魔法","vod_pic":"https://img.ffzy888.com/upload/vod/20250122-1/48644e89380665c807b708584803b7fb.jpg","vod_remarks":"更新至03集"},{"vod_name":"BanGDream!AveMujica","vod_pic":"https://img.ffzy888.com/upload/vod/20250102-1/c869512233a9fafc155008d6c71d92e1.jpg","vod_remarks":"更新至04集"},{"vod_name":"石纪元第四季","vod_pic":"https://img.ffzy888.com/upload/vod/20250109-1/9931387d50f4fbbe93889a0e17b927a7.jpg","vod_remarks":"更新至03集"},{"vod_name":"不幸职业【鉴定士】实则最强","vod_pic":"https://img.ffzy888.com/upload/vod/20250109-1/3b4b36c02494466d5746ff5e8d7d73c8.jpg","vod_remarks":"更新至03集"},{"vod_name":"中年大叔转生反派千金","vod_pic":"https://img.ffzy888.com/upload/vod/20250110-1/5fe929b33c28abd60567d2147ad64bae.jpg","vod_remarks":"更新至03集"},{"vod_name":"全修","vod_pic":"https://img.ffzy888.com/upload/vod/20250108-1/1b33521395cdfb080004b46b6ce0fed2.jpg","vod_remarks":"更新至03集"}]},{"type_name":"热播短剧","vlist":[{"vod_name":"诱你偷香","vod_pic":"https://img.ffzy888.com/upload/vod/20241202-1/8304e0aca8f7988c1b183f461369035f.jpg","vod_remarks":"已完结"},{"vod_name":"许你万千光芒好","vod_pic":"https://img.ffzy888.com/upload/vod/20241202-1/64d21f0f0a513315ac3d1594d1799741.jpeg","vod_remarks":"已完结"},{"vod_name":"不能离","vod_pic":"https://img.ffzy888.com/upload/vod/20241202-1/ec1bdcf070d4c9bb29d6e12e9b7f6a7c.jpg","vod_remarks":"已完结"},{"vod_name":"她从地狱来","vod_pic":"https://img.ffzy888.com/upload/vod/20241202-1/886dba448a20b8be8b7bb78953149336.jpg","vod_remarks":"已完结"},{"vod_name":"山河吟长歌","vod_pic":"https://img.ffzy888.com/upload/vod/20241202-1/6f4139f545d5344498b42d1bfb6a7799.jpg","vod_remarks":"已完结"},{"vod_name":"别叫我大朗","vod_pic":"https://img.ffzy888.com/upload/vod/20241202-1/65d63eb3d130a4ff521d9d1a49d077df.jpg","vod_remarks":"已完结"}]}]}';
    }

    /**
     * 清除频道缓存
     */
    public function clearCache(Request $request)
    {
        Cache::delete('useChannel');
        Cache::delete('useNav');
        return response('Cache cleared');
    }
}

