<?php
/**
 * Name:        Stock
 * Author:        weixinchen(weixinchen@tencent.com) | Shjlone
 * Revised:    13/10/2022
 * Version:        0.2
 */
require_once("workflows.php");
require_once("filecache.php");
require_once("qt.php");

class SmartBox extends Workflows
{
    private $keyword = "";
    private $queryUrl = "http://smartbox.gtimg.cn/s3/?&t=all&format=jsonp&q=";
    private $cacheDir = './tmp';

    function setKeyWord($keyword)
    {
        $this->keyword = $keyword;
    }

    function removeStock()
    {
        $cacheData = FileCache::get('__self_select_stock__', $this->cacheDir);
        foreach ($cacheData as $key => $value) {
            echo $key;
            if($key == $this->keyword) {
                unset($cacheData[$key]);
                FileCache::set('__self_select_stock__', $cacheData, 0, $this->cacheDir);
                $this->result(0, '', '移除成功', null, null);
                return;
            }
        }
    }

    function testStock() 
    {
        $this->result(0, 'test test', '添加成功', null, null, false);   
    }


    function addStock()
    {
        
        $result = explode(":", $this->keyword);//ths:sz300033
        // 构造数据
        // $url = $this->queryUrl . urlencode($this->keyword);
        // $request_result = $this->request($url);//根据关键字模糊查询
        // $json = json_decode($request_result);
        // $searchData = $json->data;
        $cacheData = FileCache::get('__self_select_stock__', $this->cacheDir);
        $cacheData[$result[0]][] = $result[1];//保存
        // if (count($searchData) > 0) {
        //     foreach ($searchData as $value) {
        //         $dCode = $this->getCode($value);
        //         var_dump($dCode);
        //         $cacheData[$this->keyword][] = $dCode;
        //     }
        // }
        // var_dump('==============>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>', $cacheData);
        $flag = FileCache::set('__self_select_stock__', $cacheData, 0, $this->cacheDir);
        // var_dump('======+++++++++++++$', $flag);
        if($flag == True) {
            $this->result(0, '', '添加成功', null, null);   
        }
        
    }


    

    function listStock() 
    {
        // echo "start---->>>>>>>>>", $this->cacheDir;
        $cacheData = FileCache::get('__self_select_stock__', $this->cacheDir);
        // var_dump($cacheData);
        // echo "end--->>>>>>>>", is_array($cacheData) ;
        // return;
        // var_dump(count($cacheData));
        $param = [];
        $searchData = [];        
        // var_dump($cacheData);
        if(is_array($cacheData)) {
            foreach ($cacheData as $key => $value) {
                // var_dump('>>>>', $key, $value);
                if(is_array($value)) {
                    // var_dump('value>>>>', $value);
                    foreach ($value as $k => $v) {
                        // var_dump('>>>>>', $k, $v);
                        array_push($param, $v);
                        $url = $this->queryUrl . urlencode($key);
                        $request_result = $this->request($url);
                        // echo '>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>', $request_result;
                        $json = json_decode($request_result, true);
                        
                        array_push($searchData, $json);
                        // return;
                    }
                    
                }
            }
        }
        
        $qt = new StockQt();
        $qt->fetchQt(implode(',', $param));
        foreach ($searchData as $key => $value) {
            foreach($value as $k => $v) {
                $stock = new Stock($v[0], $qt);
                // var_dump($stock->getTitle());
                // $stock->getLink()
                $this->result($key, $stock->pinyin, $stock->getTitle(), $stock->getSubTitle(), null);
            } 
            
            
        }

    }

    function search()
    {
        //    echo "start=========", $this->keyword;
        $cacheData = FileCache::get('__cache__' . $this->keyword);
        // echo '1111111111111111';
        //  var_dump($cacheData);
        if (!$cacheData) {
            $url = $this->queryUrl . urlencode($this->keyword);

            $request_result = $this->request($url);

            $json = json_decode($request_result);
            $searchData = $json->data;

            // var_dump($url);
            if (count($searchData) > 0) {
                FileCache::set('__cache__' . $this->keyword, $searchData, 24 * 60 * 60);
            }
        } else {
            $searchData = $cacheData;
        }

        if (count($searchData) > 0) {
            $codeArray = array();
            foreach ($searchData as $value) {
                $dCode = $this->getCode($value);
                array_push($codeArray, $dCode);
            }

            $qt = new StockQt();
            $qt->fetchQt(implode(',', $codeArray));

            foreach ($searchData as $key => $value) {
                $stock = new Stock($value, $qt);
                // var_dump($stock->pinyin, $stock->fullCode, '>>>>>>>>>');
                $this->result($key, $stock->pinyin . ':' . $stock->fullCode, $stock->getTitle(), $stock->getSubTitle(), null);
                // $this->result($key, $stock->getLink(), $stock->getTitle(), $stock->getSubTitle(), null);

            }
        } else {
            $this->lastPlaceholder();
        }
    }


    private function getCode($value)
    {
        $d = explode('~', $value);
        if (preg_match('/(\..*)$/', $d[1], $re)) {
            $d[1] = str_replace($re[1], "", $d[1]);
        }
        if ($d[0] == 'us') {
            $d[1] = strtoupper($d[1]);
        }
        $dCode = $d[0] . $d[1];
        if ($d[0] == 'hk') {
            $dCode = 'r_' . $dCode;
        }
        if ($d[0] == 'jj') {
            $dCode = 's_' . $dCode;
        }
        return $dCode;
    }

    function lastPlaceholder()
    {
        $this->result(0, 'http://gu.qq.com/i', '没有找到股票？进入我的自选股查找', null, null);
    }
}

class Stock
{
    // 市场: sh|sz|hk|us|jj
    public $market;
    // 市场类类别:
    public $typeName;
    // 代码
    public $code;
    // 详细代码
    public $fullCode;
    // 名称
    public $name;
    // 拼音
    public $pinyin;
    // 类别
    public $category;

    private $qt;

    function __construct($data, $stockQt)
    {
        // var_dump('==================2222');
        // var_dump($data);
        // var_dump('==================1111');

        $result = explode("~", $data);

        // var_dump('==================result start');
        // var_dump($result);
        // var_dump('==================result end');

        if ($result[0] == 'us') {
            if (preg_match('/(\..*)$/', $result[1], $re)) {
                $result[1] = str_replace($re[1], "", $result[1]);
            }
            $result[1] = strtoupper($result[1]);
        }
        $this->market = $result[0];
        $this->code = $result[1];
        $this->fullCode = $this->market . $this->code;
        $this->name = $result[2];
        $this->pinyin = $result[3];
        $this->category = $result[4];
        $qtData = $stockQt->getItem($this->fullCode);
        if ($qtData) {
            switch ($this->market) {
                case 'sh':
                case 'sz':
                    $this->qt = new QT($this->market, $this->category, $qtData);
                    break;
                case 'hk':
                    $this->qt = new QTHk($this->market, $this->category, $qtData);
                    break;
                case 'us':
                    $this->qt = new QTUs($this->market, $this->category, $qtData);
                    break;
                case 'jj':
                    $this->qt = new QTJj($this->market, $this->category, $qtData);
                    break;
            }
        }

        $this->parse();
    }

    private function parse()
    {
        if ($this->category == 'QH-QH') {
            $this->typeName = '期货';
        } else if ($this->category == 'QH-IF') {
            $this->typeName = '股期';
        } else if ($this->market == 'us') {
            $this->typeName = '美股';
        } else if ($this->market == 'hk') {
            $this->typeName = '港股';
        } else if ($this->market == 'jj') {
            $this->typeName = '基金';
        } else if ($this->market == 'sh' || $this->market == 'sz') {
            switch ($this->category) {
                case 'FJ':
                case 'LOF':
                case 'ETF':
                    $this->typeName = '基金';
                    break;
                case 'ZS':
                case 'GP-A':
                case 'GP-B':
                case 'ZQ':
                case 'QZ':
                default:
                    if ($this->market == 'sh') {
                        $this->typeName = '上海';
                    } else {
                        $this->typeName = '深圳';
                    }
                    break;
            }
        } else {
            $this->typeName = '未知';
        }
    }

    function getTitle()
    {
        $typeName = $this->typeName;
        $name = $this->name;
        $code = $this->code;

        $return = sprintf("[%s] %-20s %-12s", $typeName, $name, $code);
        if ($this->qt) {
            if (!$this->qt->getErrorStatus()) {
                $price = $this->qt->getPrice();
                if ($this->market != 'jj') {
                    $percent = $this->qt->getPercent();
                    $return .= sprintf(" %-12s %-12s", $price, $percent);
                } else {
                    if (!$this->qt->isHBType()) {
                        $return .= sprintf(" 净值:%-12s", $price);
                    } else {
                        $price = $this->qt->getEarnPer();
                        $return .= sprintf(" 万份收益:%-12s", $price);
                    }
                }
            } else {
                $status = $this->qt->getErrorStatus();
                $return .= " {$status}";
            }
        }

        return $return;
    }

    function getSubTitle()
    {
        $fullCode = $this->fullCode;

        $return = "{$fullCode}";
        if ($this->pinyin != '*') {
            $pinyin = strtoupper($this->pinyin);
            $return .= "（{$pinyin}）";
        }

        if ($this->qt) {
            if (!$this->qt->getErrorStatus()) {
                if ($this->market != 'jj') {
                    $lastClose = $this->qt->getLastClosePrice();
                    $todayOpen = $this->qt->getTodayOpenPrice();
                    $hPrice = $this->qt->getHighPrice();
                    $lPrice = $this->qt->getLowPrice();

                    $return .= " 高:{$hPrice}  低:{$lPrice}  收:{$lastClose}  开:{$todayOpen}";
                } else {
                    if (!$this->qt->isHBType()) {
                        $valueDate = $this->qt->getValueDate();
                        $return .= " 净值更新时间:{$valueDate}";
                    } else {
                        $yearRadio = $this->qt->getYearRadio();
                        $return .= " 七日年化收益率:{$yearRadio}";
                    }
                }
            }
        }

        return $return;
    }

    function getLink()
    {
        return "http://gu.qq.com/" . $this->fullCode;
    }
}


class StockQt
{
    protected $items = array();

    //查询行情数据
    public function fetchQt($stock_code)
    {
        $url = 'http://qt.gtimg.cn/q=' . $stock_code;
        $data = $this->getCurlData($url, 80, 2);
        $data = iconv("GB2312", "UTF-8//IGNORE", $data);
        $data = trim($data);
        $edatas = explode(';', $data);

        $codes = array();
        foreach ($edatas as $value) {
            $it = explode('~', $value);
            if (trim($it[0])) {
                preg_match('/_([^_]*?)\=/', $it[0], $result);
                $this->items[$result[1]] = $it;
            }
        }
    }

    //获得数据
    public function getItem($code)
    {
        $data = $this->items[$code];
        if ($data) {
            return $data;
        } else {
            return false;
        }
    }

    private function getCurlData($url, $port = 80, $timeout = 10)
    {
        $ch = curl_init();
        // set port
        curl_setopt($ch, CURLOPT_PORT, $port);
        // drop http header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        // get data as string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        // set timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        // execute fetch
        $data = curl_exec($ch);
        $errno = curl_errno($ch);
        if ($errno > 0) {
            //try one time
            $data = curl_exec($ch);
            $errno = curl_errno($ch);
            if ($errno > 0) {
                return false;
            }
        }
        if (empty($data)) {
            return array();
        }
        return $data;
    }
}

?>
