<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Content;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;
use xingwenge\canal_php\CanalConnectorFactory;
use xingwenge\canal_php\CanalClient;
use xingwenge\canal_php\Fmt;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\DbConnection\Db;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @Command
 */
class DownloadFileCommand extends BaseCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
        $this->setName("down");
    }

    public function configure()
    {
        $this->setDescription('download files');
    }

    protected $coroutine = false;

    public function handle()
    {
//        $a = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
//        print_r($a);
//        die;

        $str = "https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/Editormd/editormd.min.css?ver=2.0.1 https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/Config/editormd.min.css?ver=10.0.3 https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/Prism.js/themes/prism.css https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/CodeMirror/lib/codemirror.css https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/CodeMirror/addon/dialog/dialog.css https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/CodeMirror/addon/search/matchesonscrollbar.css https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/Turndown/turndown.js?ver=5.0.1 https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/Editormd/editormd.min.js?ver=2.0.1 https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/Config/editormd.min.js?ver=10.0.3 http://blog.gkfk5.cn/wp-includes/js/shortcode.min.js?ver=5.2.2 http://blog.gkfk5.cn/wp-includes/js/wp-util.min.js?ver=5.2.2 http://blog.gkfk5.cn/wp-admin/js/svg-painter.js?ver=5.2.2 https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/CodeMirror/lib/codemirror.js https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/Editormd/lib/modes.min.js https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/Editormd/lib/addons.min.js https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/Marked/marked.min.js https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/Editormd/lib/prism.min.js https://cdn.jsdelivr.net/wp/wp-editormd/tags/10.0.3/assets/MindMap/mindMap.min.js https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js";

        $arr = explode(" ", $str);

        $http_client = $this->container->get(ClientFactory::class)->create();
//        $rt = $http_client->get($url)->getBody()->getContents();

//        print_r($arr);
//        return '';

        $this->dTime('start');
//        print_r($arr);

        $content_model = new Content();
        $content_model->form_data = 123;

        $rt = $content_model->save();
        var_dump($rt);

        echo 12312312312321;

        print_r($rt);
        return '';


        $rt = DB::select("show tables;");

        print_r($rt);

        return '';

        foreach ($arr as $k => $url) {
            $url = trim($url);
            try {
                $resp = $http_client->get($url)->getBody()->getContents();


                $this->dTime('zxc');
            }catch(\Exception $e){
                $this->line($url);
            }
        }

        $as = $this->dTime(true);
        print_r($as);


//        $this->line('Hello Hyperf!', 'info');
    }

    private function makeDirectory($path)
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        return $path;
    }


    public function dTime($tag = "")
    {
        static $list = [];
        if (is_bool($tag) && $tag === true) {
            $tmp = $list;
            $list = [];
            return $tmp;
        }
        $time = microtime(true);
        if (isset($list[$tag])) {
            $end = end($list[$tag]);
            if (strpos((string)$end, "---") !== false) {
                list($end, $noon) = explode("---", $end);
            }
            $num = $time - $end;
            $num = number_format((float)$num, 6, ".", "");
            $time = $time . "---" . $num;
        }
        $list[$tag][] = $time;
    }
}
