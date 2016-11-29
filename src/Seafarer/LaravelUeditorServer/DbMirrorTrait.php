<?php

namespace Seafarer\LaravelUeditorServer;

trait DbMirrorTrait {

    /**
     * 本地数据库文件索引存储
     */
    public function dbMirrorSave($response)
    {
        \DB::transaction(function() use($response) {
            \DB::table('ueditor_images')->insert([
                'url' => $response['url'],
                'title' => $response['title'],
                'original' => $response['original'],
                'type' => $response['type'],
                'size' => $response['size'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        });
    }

    /**
     * 本地数据库文件索引拉取
     */
    public function dbMirrorList($allowFiles, $listSize, $path)
    {
        $allowFiles = substr(str_replace(".", "|", join("", $allowFiles)), 1);

		/* 获取参数 */
		$size  = \Input::get('size', $listSize);
		$start = \Input::get('start', 0);
        $page = intval($start / $size) + 1;
        \Input::merge(['page' => $page]);

        $list = \DB::table('ueditor_images')->orderBy('created_at', 'DESC')->paginate($size)->all();
        $count = \DB::table('ueditor_images')->count();
        $result = json_encode(array(
			"state" => "SUCCESS",
			"list"  => $list,
			"start" => $start,
			"total" => $count
		));

		return $result;
    }
}
