<?php
/**
 * Description of Controller
 *
 * @author david marsalone
 */
class Controller {
    

    const OUTPUT_JSON="json";
    const OUTPUT_XML="xml";
    const OUTPUT_HTML="html";
    
    /**
     *
     * @var Nerd_Header The header according to the output (json, xml, html)
     */
    public $headerType;
    /**
     *
     * @var Nerd_Header The header according to the status (404,301...)
     */
    public $headerCode;
    
    /**
     * 
     * @var ViewVariables The data object that will feed the View.
     */
    public $data;

    /**
     *
     * @var String the function name to call in the controller 
     */
    public $routeFunction="index";
    /**
     *
     * @var Array The parameters passed in the url 
     */
    public $routeParams=array();
    
    /**
     *
     * @var String the requested output type for this controler.
     * 
     * @see OUTPUT_JSON
     * @see OUTPUT_XML
     * @see OUTPUT_HTML
     */
    public $outputType=self::OUTPUT_HTML;
    
    /**
     *
     * @var string The original route wich led to this controller
     * @exemple my-controller-path/my-controller/my-function-in-the-controler/parameter-one/parameter-N 
     */
    public $route="";
 
    
    /**
     *
     * @var string  The route without the function. Very usefull to performs redirections.
     * @exemple my-controller-path/my-controller
     */
    public $routeToController="";
    
    /**
     *
     * @var string  The route without parameters. Very usefull to performs redirections.
     * @exemple my-controller-path/my-controller/my-function-in-the-controler 
     */
    public $routeToFunction="";


    
    /**
     * convert an url(splitted by folders) in an url that could match a controller. 
     * @param array $parts
     * @return string 
     * @exemple [toto,titi,tata] will return _app/mvc/c/toto/titi/c_tata.php
     */
    private static function getControlerPath($parts){
        $controllerFile=array_pop($parts);
        $controllerFile="/c_".$controllerFile.".php";
        $url=  Site::$appControllersFolder."/".implode("/", $parts).$controllerFile;
        return $url;
    }


    /**
     * Return a controller based on a route.
     * @param String $route an internal url that looks like : /path/to/controler/controlerName/function-in-the-controler/param1/param2/paramN
     * @return Controller The related controller
     */
    public static function getByRoute($route){
        
        $savedRoute=$route;
        
        //find and remove extension from route
        preg_match_all("|^(.*)\.([a-zA-Z0-9].{0,5})$|",$route,$out);
        if($out && $out[0]){
            $route=$out[1][0];
            $ext=$out[2][0];
        }

	$parts=explode("/",$route);

        //search the controller php file
        for($i=0;$i<count($parts);$i++){
            $path="/".implode("/",array_slice($parts, 0, $i+1));
            $fileName= preg_replace("/^(.*)\/(.*)$/", "$1/c_$2.php", $path);
            $file = Site::$appControllersFolder.$fileName;
            
            if(file_exists($file)){
                $phpFile=$file;
                break;
            }else{
                $file = Site::$systemControllersFolder.$fileName; 
                if(file_exists($file)){
                    $phpFile=$file;
                    break;
                }  
            }

            
        }
        
        

        // file not found
        if(!$phpFile){
            return false;
        }

        $className=ucfirst($parts[$i])."Controller";
        $fn=$parts[++$i];
        $params=  array_slice($parts, ++$i);
        $routeToController=implode("/",array_slice($parts,0,$i-1));
        
        

        /*
        echo "------------->route : ".$route."<br/>";
        echo "------------->php File : ".$phpFile."<br/>";
        echo "------------->className : ".$className."<br/>";
        echo "-------------> function : ".$fn."<br/>";
        echo "-------------> params : ".implode(",",$params)."<br/>"; 
        echo "-------------> extension : ".$ext."<br/>"; 
        echo "-------------> route to Controller : ".$routeToController."<br/>"; 
        die();
        */ 
         
        //we have a file that match but maybe class or function are not correct
        $controller=self::getController($phpFile,$className,$fn);
        
        if($controller){
            //give parameters to the controller
            $controller->routeParams=$params;
            
            $controller->setOutputType($ext);
            $controller->extension=$ext;
            $controller->route=$savedRoute;
            
            $controller->routeToController=$routeToController;
            $controller->routeToFunction=$routeToController."/".$fn;
            
            return $controller;
        }


	
        

        return false;
    }
    /**
     * Return a Contoller if this one is valid according to the parameters.
     * @param string $file the php file where is the controller.
     * @param string $className the controller class name.
     * @param string $functionName the function to launch in the controller.
     * @return boolean|Controller return the controller if this one is valid, else return false.
     */
    private static function getController($file,$className,$functionName){

        //include the controler
        require_once "$file";
        
        //check for class name
        if(!class_exists($className)){
            return false;
        }

        //creates the controler
        $controller=new $className();
        
        //check for the function inside the controller
        if(method_exists ( $controller, $functionName )){
            $controller->routeFunction=$functionName;
        }else{
            return false;
        }
        
        //you win!
        return $controller;
    }
    
    /**
     * The page will have an header 404
     */
    public function setHeader404(){
        $this->headerCode=new Nerd_Header(Nerd_Header::ERR_404);
    }
    /**
     * The page will have an header 301 and will be redirected to $redirectUrl.
     * @param string $redirectUrl The url where to go.
     */
    public function redirect301($redirectUrl){ 
        $this->headerCode=new Nerd_Header(Nerd_Header::REDIRECT_301, Site::url($redirectUrl,true));    
    }
    /**
     * The page will have an header 302 and will be redirected to $redirectUrl
     * @param string $redirectUrl The url where to go. 
     */
    public function redirect302($redirectUrl){ 
        $this->headerCode=new Nerd_Header(Nerd_Header::REDIRECT_302, Site::url($redirectUrl,true));
    }

    /**
     * Define the outputType and the headerType. In fact it can be json or xml elsewhere it will be an html output.
     * @param string $extension something like json or xml
     */
    private function setOutputType($extension){
        
        switch ($extension){
            case self::OUTPUT_JSON:
                $this->outputType=$extension;
                $this->headerType=new Nerd_Header(Nerd_Header::JSON);
                break;
            
            case self::OUTPUT_XML:
                $this->outputType=$extension;
                $this->headerType=new Nerd_Header(Nerd_Header::XML);
                Human::log("set extension ".$extension);
                break;
            
           default:
                $this->outputType=self::OUTPUT_HTML;
                break;
        }
    }

    /**
     * process the controller with the current function and parameters.
     * @return View the resulting view object
     */
    public function run(){
        $view=call_user_func_array(array($this,$this->routeFunction), $this->routeParams);
        return $view;
    }
    
    
    /**
     * return the default 404 error page.
     *  
     */
    public function default404(){
        return new View("404",new VV_404());
    }
    

}
