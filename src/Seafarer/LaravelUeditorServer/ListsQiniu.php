<?php namespace Seafarer\LaravelUeditorServer;

use \Qiniu\Storage\BucketManager;
use \Qiniu\Auth;
use \Config;

class ListsQiniu
{
    public function __construct($allowFiles, $listSize, $path, $request)
    {
        $this->allowFiles = substr(str_replace(".", "|", join("", $allowFiles)), 1);
        $this->listSize = $listSize;
        $this->path = ltrim($path,'/');
        $this->localPath  = $_SERVER['DOCUMENT_ROOT'] . $path;
        $this->request = $request;
    }

    public function getTotal()
    {
        $files = $this->getfiles($this->localPath, $this->allowFiles);
        return count($files);
    }

    /**
     * 一般情况下，向qiniu请求的资源都可以在镜像源获取
     * 因此本地的文件索引即可视为qiniu的文件索引
     */
    public function getList()
    {
        $total = $this->getTotal();
        $size = $this->request->get('size', $this->listSize);
        $start = $this->request->get('start', 0);
        $end   = intval($start) + intval($size);
        $files = $this->getfiles($this->localPath, $this->allowFiles);

        if (!count($files)) {
            return [
                "state" => "no match file",
                "list"  => array(),
                "start" => $start,
                "total" => $total
            ];
        }

        /* 获取指定范围的列表 */
        $len = $total;
        for ($i = min($end, $len) - 1, $list = array(); $i<$len && $i>=0 && $i>=$start; $i--) {
            $files[$i]['url'] = Config::get('laravel-ueditor-server::core.qiniu')['url'] . $files[$i]['url'];
            $list[] = $files[$i];
        }
        //倒序
        //for ($i = $end, $list = array(); $i < $len && $i < $end; $i++){
        //    $list[] = $files[$i];
        //}

        /* 返回数据 */
        $result = [
            "state" => "SUCCESS",
            "list"  => $list,
            "start" => $start,
            "total" => $total
        ];

        return $result;
    }

    /**
     * 获取qiniu空间中的文件列表
     */
    public function getRemoteList()
    {
        $total = $this->getTotal();
        $size = $this->request->get('size', $this->listSize);
        $start = $this->request->get('start', '');
        $auth = new Auth(Config::get('laravel-ueditor-server::core.qiniu')['accessKey'], Config::get('laravel-ueditor-server::core.qiniu')['secretKey']);

        $bucketManager = new BucketManager($auth);
        list($items, $marker, $error) = $bucketManager->listFiles(Config::get('laravel-ueditor-server::core.qiniu')['bucket'], $this->path, $start, $size);

        if ($error) {
            return [
                "state" => $error->message(),
                "list" => array(),
                "start" => $start,
                "total" => 0
            ];
        }
        if(empty($items)){
            return [
                "state" => "no match file",
                "list" => array(),
                "start" => $start,
                "total" => 0
            ];
        }

        $files=[];
        foreach ($items as  $v) {
            if (preg_match("/\.(" . $this->allowFiles . ")$/i", $v['key'])) {
                $files[] = array(
                    'url' =>rtrim(Config::get('laravel-ueditor-server::core.qiniu')['url'],'/').'/'.$v['key'],
                    'mtime' => $v['mimeType'],
                );
            }
        }
        if(empty($files)){
            return [
                "state" => "no match file",
                "list" => array(),
                "start" => $start,
                "total" => 0
            ];
        }
        /* 返回数据 */
        $result = [
            "state" => "SUCCESS",
            "list" => $files,
            "start" => $start,
            "total" => count($files)
        ];

        return $result;
    }

    /**
     * 遍历获取目录下的指定类型的文件
     * @param $path
     * @param array $files
     * @return array
     */
    protected function  getfiles($path, $allowFiles, &$files = array())
    {

        if (!is_dir($path)) return null;
        if (substr($path, strlen($path) - 1) != '/') $path .= '/';
        $handle = opendir($path);
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $path2 = $path . $file;
                if (is_dir($path2)) {
                    $this->getfiles($path2, $allowFiles, $files);
                } else {
                    if (preg_match("/\.(" . $allowFiles . ")$/i", $file)) {
                        $files[] = array(
                            'url' => substr($path2, strlen($_SERVER['DOCUMENT_ROOT'])),
                            'mtime' => filemtime($path2)
                        );
                    }
                }
            }
        }
        return $files;
    }

}
