<?php
use Seafarer\LaravelUeditorServer\UeditorUploader;
use Seafarer\LaravelUeditorServer\ListsQiniu;

class UeditorController extends BaseController {

    /**
	 * 默认的上传方式
	 * @var string
	 */
	private $base64 = "upload";

	/**
	 * 处理请求信息
	 */
	public function getAction() {
		$action = Input::get('action');

		switch ($action) {
			/* 前后端通信的配置信息 */
			case 'config':
				return $this->configBackend();
			/* 上传图片 */
			case 'uploadimage':
				return $this->postUploadimage();
			/* 上传涂鸦 */
			case 'uploadscrawl':
				return $this->postUploadscrawl();
			/* 上传视频 */
			case 'uploadvideo':
				return $this->postUploadvideo();
			/* 上传文件 */
			case 'uploadfile':
				return $this->defaultUpload();
			/* 列出图片 */
			case 'listimage':
				return $this->listImages();
			/* 列出文件 */
			case 'listfile':
				return $this->listFiles();
			/* 抓取远程文件 */
			case 'catchimage':
				return $this->catchImages();
		}

		return Response::json(['state' => '您的请求没有被处理！']);
	}

	/**
	 * 前后端配置文件通信
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function configBackend() {
		return Response::json(Config::get('laravel-ueditor-server::upload'));
	}

	/**
	 * 上传图片
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function postUploadimage() {
		$config    = [
			"pathFormat" => Config::get('laravel-ueditor-server::upload.imagePathFormat'),
			"maxSize"    => Config::get('laravel-ueditor-server::upload.imageMaxSize'),
			"allowFiles" => Config::get('laravel-ueditor-server::upload.imageAllowFiles')
		];
		$fieldName = Config::get('laravel-ueditor-server::upload.imageFieldName');
		$up        = new UeditorUploader($fieldName, $config, $this->base64);

		return Response::json($up->getFileInfo());
	}

	/**
	 * 上传涂鸦
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function postUploadscrawl() {
		$config       = [
			"pathFormat" => Config::get('laravel-ueditor-server::upload.scrawlPathFormat'),
			"maxSize"    => Config::get('laravel-ueditor-server::upload.scrawlMaxSize'),
			"oriName"    => "scrawl.png"
		];
		$fieldName    = Config::get('laravel-ueditor-server::upload.scrawlFieldName');
		$this->base64 = "base64";
		$up           = new UeditorUploader($fieldName, $config, $this->base64);

		return Response::json($up->getFileInfo());
	}

	/**
	 * 上传视频
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function postUploadvideo() {
		$config    = [
			"pathFormat" => Config::get('laravel-ueditor-server::upload.videoPathFormat'),
			"maxSize"    => Config::get('laravel-ueditor-server::upload.videoMaxSize'),
			"allowFiles" => Config::get('laravel-ueditor-server::upload.videoAllowFiles')
		];
		$fieldName = Config::get('laravel-ueditor-server::upload.videoFieldName');

		$up = new UeditorUploader($fieldName, $config, $this->base64);

		return Response::json($up->getFileInfo());
	}

	/*
	 * 此处包括了 postUploadfile 方法
	 */
	/**
	 * @param array $parameters
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function defaultUpload($parameters = array()) {
		$config    = [
			"pathFormat" => Config::get('laravel-ueditor-server::upload.filePathFormat'),
			"maxSize"    => Config::get('laravel-ueditor-server::upload.fileMaxSize'),
			"allowFiles" => Config::get('laravel-ueditor-server::upload.fileAllowFiles')
		];
		$fieldName = Config::get('laravel-ueditor-server::upload.fileFieldName');
		$up        = new UeditorUploader($fieldName, $config, $this->base64);

		return Response::json($up->getFileInfo());
	}

	/**
	 * 远程获取图片
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function catchImages() {
		$config    = [
			"pathFormat" => Config::get('laravel-ueditor-server::upload.catcherPathFormat'),
			"maxSize"    => Config::get('laravel-ueditor-server::upload.catcherMaxSize'),
			"allowFiles" => Config::get('laravel-ueditor-server::upload.catcherAllowFiles'),
			"oriName"    => "remote.png"
		];
		$fieldName = Config::get('laravel-ueditor-server::upload.catcherFieldName');

		/* 抓取远程图片 */
		$list = [];
		if (isset($_POST[$fieldName])) {
			$source = $_POST[$fieldName];
		} else {
			$source = $_GET[$fieldName];
		}
		foreach ($source as $imgUrl) {
			$item = new UeditorUploader($imgUrl, $config, "remote");
			$info = $item->getFileInfo();
			array_push($list, [
				"state"    => $info["state"],
				"url"      => $info["url"],
				"size"     => $info["size"],
				"title"    => htmlspecialchars($info["title"]),
				"original" => htmlspecialchars($info["original"]),
				"source"   => htmlspecialchars($imgUrl)
			]);
		}

		/* 返回抓取数据 */

		return Response::json([
			'state' => count($list) ? 'SUCCESS' : 'ERROR',
			'list'  => $list
		]);
	}

	/**
	 * 列出全部文件
	 *
	 * @return string
	 */
	public function listFiles() {
		$allowFiles = Config::get('laravel-ueditor-server::upload.fileManagerAllowFiles');
		$listSize   = Config::get('laravel-ueditor-server::upload.fileManagerListSize');
		$path       = Config::get('laravel-ueditor-server::upload.fileManagerListPath');

		return $this->processList($allowFiles, $listSize, $path);
	}

	/**
	 * 列出图片
	 *
	 * @return string
	 */
	public function listImages() {
		$allowFiles = Config::get('laravel-ueditor-server::upload.imageManagerAllowFiles');
		$listSize   = Config::get('laravel-ueditor-server::upload.imageManagerListSize');
		$path       = Config::get('laravel-ueditor-server::upload.imageManagerListPath');

        if (Config::get('laravel-ueditor-server::core.mode') == 'local') {
		    return $this->processList($allowFiles, $listSize, $path);
        } else if (Config::get('laravel-ueditor-server::core.mode') == 'qiniu') {
            $result = with(new ListsQiniu($allowFiles, $listSize, $path, App::make('Illuminate\Http\Request')))->getList();
            return Response::json($result, 200, [], JSON_UNESCAPED_UNICODE);
        }
	}

	/**
	 * 处理文件列表
	 *
	 * @param $allowFiles
	 * @param $listSize
	 * @param $path
	 *
	 * @return string
	 */
	private function processList($allowFiles, $listSize, $path) {
		$allowFiles = substr(str_replace(".", "|", join("", $allowFiles)), 1);

		/* 获取参数 */
		$size  = isset($_GET['size']) ? htmlspecialchars($_GET['size']) : $listSize;
		$start = isset($_GET['start']) ? htmlspecialchars($_GET['start']) : 0;
		$end   = intval($start) + intval($size);

		/* 获取文件列表 */
		$path  = $_SERVER['DOCUMENT_ROOT'].(substr($path, 0, 1) == "/" ? "" : "/").$path;
		$files = $this->getfiles($path, $allowFiles);
		if (!count($files)) {
			return json_encode(array(
				"state" => "no match file",
				"list"  => array(),
				"start" => $start,
				"total" => count($files)
			));
		}

		/* 获取指定范围的列表 */
		$len = count($files);
		for ($i = min($end, $len) - 1, $list = array(); $i<$len && $i>=0 && $i>=$start; $i--) {
			$list[] = $files[$i];
		}
		//倒序
		//for ($i = $end, $list = array(); $i < $len && $i < $end; $i++){
		//    $list[] = $files[$i];
		//}

		/* 返回数据 */
		$result = json_encode(array(
			"state" => "SUCCESS",
			"list"  => $list,
			"start" => $start,
			"total" => count($files)
		));

		return $result;
	}

	/**
	 * 遍历获取目录下的指定类型的文件
	 *
	 * @param       $path
	 * @param       $allowFiles
	 * @param array $files
	 *
	 * @return array
	 */
	private function getfiles($path, $allowFiles, &$files = array()) {
		if (!is_dir($path))
			return null;
		if (substr($path, strlen($path) - 1) != '/')
			$path .= '/';
		$handle = opendir($path);
		while (false !== ($file = readdir($handle))) {
			if ($file != '.' && $file != '..') {
				$path2 = $path.$file;
				if (is_dir($path2)) {
					$this->getfiles($path2, $allowFiles, $files);
				} else {
					if (preg_match("/\.(".$allowFiles.")$/i", $file)) {
						$files[] = array(
							'url'   => substr($path2, strlen($_SERVER['DOCUMENT_ROOT'])),
							'mtime' => filemtime($path2)
						);
					}
				}
			}
		}

		return $files;
	}

	/**
	 * 获取前后端通信的配置信息
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function config() {
		$config = '(function () {
            window.UEDITOR_CONFIG =';
		$config .= json_encode(Config::get('laravel-ueditor-server::editor'));
		$config .= ';';

		//$config = file_get_contents('./packages/zhuzhichao/ueditor/ueditor.config.js');

		$config .= <<<js
function getUEBasePath(docUrl, confUrl) {
	return getBasePath(docUrl || self.document.URL || self.location.href, confUrl || getConfigFilePath());
}
function getConfigFilePath() {
	var configPath = document.getElementsByTagName('script');
	return configPath[ configPath.length - 1 ].src;
}
function getBasePath(docUrl, confUrl) {
	var basePath = confUrl;
	if (/^(\/|\\\\)/.test(confUrl)) {
		basePath = /^.+?\w(\/|\\\\)/.exec(docUrl)[0] + confUrl.replace(/^(\/|\\\\)/, '');
	} else if (!/^[a-z]+:/i.test(confUrl)) {
		docUrl = docUrl.split("#")[0].split("?")[0].replace(/[^\\\/]+$/, '');
		basePath = docUrl + "" + confUrl;
	}
	return optimizationPath(basePath);
}

function optimizationPath(path) {
	var protocol = /^[a-z]+:\/\//.exec(path)[ 0 ],
		tmp = null,
		res = [];
	path = path.replace(protocol, "").split("?")[0].split("#")[0];
	path = path.replace(/\\\/g, '/').split(/\//);
	path[ path.length - 1 ] = "";
	while (path.length) {
		if (( tmp = path.shift() ) === "..") {
			res.pop();
		} else if (tmp !== ".") {
			res.push(tmp);
		}
	}

	return protocol + res.join("/");

}

window.UE = {
	getUEBasePath: getUEBasePath
};
js;

		$config .= '})();';

		return Response::make($config, 200, ['Content-Type' => 'text/javascript']);
	}

}
