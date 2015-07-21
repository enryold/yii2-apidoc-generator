<?php
/**
 * Created by PhpStorm.
 * User: eold
 * Date: 17/07/15
 * Time: 14:48
 */


namespace eold\apidocgen\src;


use yii\base\ActionEvent;
use yii\base\Application;
use yii\base\Component;
use yii\base\Controller;
use Yii;



class ApiDocGenerator extends Component {


    /**
     * @var string $versionRegexFind pattern to find to get version name in Apidoc.js format (1.0.0) (In yii format tipically we have 'v1')
     */
    public $versionRegexFind = "";

    /**
     * @var string $versionRegexReplace pattern to replace to get version name in Apidoc.js format (1.0.0) (In yii format tipically we have 'v1')
     */
    public $versionRegexReplace = "";

    /**
     * @var bool $isActive flag to set component active or not. Useful for debug purposes.
     */
    public $isActive = false;

    /**
     * @var string $docDataAlias Alias of data_path folder, used to store generated files
     */
    public $docDataAlias = "@runtime/data_path";



    /** @var array existing doc files. Loaded on init */
    private $docs = [];

    /** @var string Friendly file name to store generated file */
    private $friendlyName = "";

    /** @var array Output file, every row is one line. */
    private $out = [];








    public function init()
    {
        if(!$this->isActive) { return; }


        $this->loadGeneratedDocs();


        /** Trigger on after action  */
        Yii::$app->on(Controller::EVENT_AFTER_ACTION,function($event){

            if(Yii::$app->request->getMethod() == 'OPTIONS') { return; }

            $this->getFriendlyName($event);

            if($this->checkIfDocExists()) { return; }

            $this->buildTagApi($event);
            $this->buildTagApiName($event);
            $this->buildTagApiGroup($event);
            $this->buildTagApiVersion($event);
            $this->buildTagApiParam($event);

        });

        /** Trigger on after request  */
        Yii::$app->on(Application::EVENT_AFTER_REQUEST,function($event){

            if(Yii::$app->request->getMethod() == 'OPTIONS') { return; }

            if($this->checkIfDocExists()) { return; }

            $this->buildTagApiSuccess();
            $this->buildTagApiSuccessExample();
            $this->writeFile();

        });




    }




    /** @param $event ActionEvent */
    public function buildTagApi($event)
    {
        $httpMethod = Yii::$app->request->getMethod();

        $calledUrl = $this->getCurrentRequestUrl();
        $module = $event->action->controller->module->id;

        $params = $event->action->controller->actionParams;
        $name = $calledUrl;

        foreach($params as $k=>$v)
        {
            $name = str_replace("/".$v,"", $calledUrl);
            $calledUrl = str_replace($v,":".$k, $calledUrl);
        }

        $name = str_replace("/".$module, "", $name);

        $pieces = explode("/", $name);
        $name = implode(" ", $pieces);

        $this->insert( '* @api {'.strtolower($httpMethod).'} '.$calledUrl. " ".$name);
    }


    /** @param $event ActionEvent */
    public function buildTagApiName($event)
    {
        $httpMethod  = strtolower(Yii::$app->request->getMethod());

        $module = $event->action->controller->module->id;
        $calledUrl = $this->getCurrentRequestUrl();
        $calledUrl = str_replace("/".$module, "", $calledUrl);
        $params = $event->action->controller->actionParams;


        foreach($params as $k=>$v) { $calledUrl = str_replace("/".$v,"", $calledUrl); }

        $pieces = explode("/", $calledUrl);

        $string = $httpMethod;

        foreach($pieces as $p) { $string .= ucfirst($p); }

        $this->insert('* @apiName '.$string);
    }


    /** @param $event ActionEvent */
    public function buildTagApiGroup($event)
    {
        $controller = ucfirst($event->action->controller->id);
        $this->insert('* @apiGroup '.$controller);
    }


    /** @param $event ActionEvent */
    public function buildTagApiVersion($event)
    {
        $version = $event->action->controller->module->id;
        $version = preg_replace($this->versionRegexFind, $this->versionRegexReplace, $version);
        $this->insert('* @apiVersion '.$version);
    }


    /** @param $event ActionEvent */
    public function buildTagApiParam($event)
    {
        $actionParams = ($event->action->controller->actionParams);

        $params = (count($actionParams)) ? $actionParams : json_decode(Yii::$app->request->getRawBody(), true);

        if(!is_array($params)) { return; }

        foreach($params as $k=>$v)
        {
            $string = '* @apiParam {'.gettype($v).'} '.$k;
            $this->insert($string);
        }

    }


    /** @param $event ActionEvent */
    public function buildTagApiSuccess()
    {
        $data = Yii::$app->response->data;
        $this->apiSuccessParam($data, "");
    }

    public function buildTagApiSuccessExample()
    {
        $data = Yii::$app->response->data;

        $this->insert('* @apiSuccessExample {json} JSON success response EXAMPLE:');
        $this->insert('* HTTP/1.1 200 OK');

        $array = (is_array($data)) ? $data : (array) $data;

        if(count($array) == 0)
        {
            $this->insert("* NO DATAS IN RESPONSE");
            return;
        }

        $var = "*";
        $var .= json_encode($data, JSON_PRETTY_PRINT);
        $var = preg_replace("/\r\n|\r|\n/",'\n *',$var);

        foreach(explode('\n', $var) as $chunk)
        {
            $this->insert($chunk);
        }
    }


    /**
     *
     * Recursive function to build ApiSuccessParams from data response.
     *
     * @param $array
     * @param string $string
     */
    private function apiSuccessParam($array, $string = "")
    {
        if(!is_array($array))
        {
            return;
        }

        foreach($array as $k=>$v)
        {
            if(is_array($v) && !is_numeric($k))
            {
                if($string == "")
                {
                    $str = '* @apiSuccess {'.gettype($v).'} '.$k;
                }
                else
                {
                    $str = preg_replace('/{([^{|}]*)}/','{'.gettype($v).'}',$string.".".$k);
                }

                $this->insert($str);

                $this->apiSuccessParam($v, $str);
            }
            else if(!is_array($v) && !is_numeric($k))
            {
                if($string == "")
                {
                    $str = '* @apiSuccess {'.gettype($v).'} '.$k;
                }
                else
                {
                    $string = preg_replace('/{([^{|}]*)}/','{'.gettype($v).'}',$string);
                    $str = $string.".".$k;
                }

                $this->insert($str);
            }
            else if($k == 0)
            {
                if(is_array($v))
                {
                    $this->apiSuccessParam($v, $string);
                }
                else
                {
                    $this->insert($string);
                }

            }

        }
    }


    /**
     * Get current url request, excluding params after '?'
     * @return string
     */
    private function getCurrentRequestUrl()
    {
        $url = Yii::$app->request->getUrl();
        $qMarkPos = strpos($url, '?');
        return substr($url, 0, $qMarkPos);
    }


    /**
     * Get current action's Friendly Name
     * @param $event ActionEvent
     */
    private function getFriendlyName($event)
    {
        $httpMethod  = Yii::$app->request->getMethod();
        $friendlyName = $httpMethod.$this->getCurrentRequestUrl();
        $params = $event->action->controller->actionParams;

        foreach($params as $k=>$v)
        {
            $friendlyName =  str_replace("/".$v,"", $friendlyName);
        }

        $friendlyName = str_replace("/", "-", $friendlyName);
        $friendlyName = substr($friendlyName, 0, strlen($friendlyName));


        $this->friendlyName = $friendlyName.".php";
    }



    /**
     * @param $str string Insert one row in output.
     */
    private function insert($str)
    {
        if(array_search($str, $this->out) === false)
        {
            $this->out[] = $str;
        }
    }


    /**
     * Load generated docs, to prevent overwrites.
     */
    private function loadGeneratedDocs()
    {
        $dataDir = Yii::getAlias($this->docDataAlias);


        if (!file_exists($dataDir)) { mkdir($dataDir, 0777, true); }

        $d = dir($dataDir);
        while($file = $d->read()) { // do this for each file in the directory
            if ($file != "." && $file != "..") { // to prevent an infinite loop
                $this->docs[$file] = true;
            }
        }
    }


    /**
     * @return bool Check if current files already exists.
     */
    private function checkIfDocExists()
    {
        return isset($this->docs[$this->friendlyName]);
    }


    /**
     * Write output file
     */
    private function writeFile()
    {
        if(isset($this->docs[$this->friendlyName])) return;


        $path = Yii::getAlias($this->docDataAlias) . DIRECTORY_SEPARATOR . $this->friendlyName;

        $doc = fopen($path, "w") or die("Unable to open file!");

        fwrite($doc, "<?php"."\n");
        fwrite($doc, "/**"."\n");

        foreach($this->out as $row)
        {
            fwrite($doc, $row."\n");
        }

        fwrite($doc, "*/"."\n");

        fclose($doc);
        chmod($path, 0777);
    }









}