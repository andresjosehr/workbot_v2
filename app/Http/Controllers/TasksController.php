<?php

namespace App\Http\Controllers;
use GuzzleHttp\Client;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Proyect;

class TasksController extends Controller
{

    public function triggerAlerts(\Goutte\Client $client)
    {
        $results = [];
        foreach (Proyect::where("active", 1)->get()->toArray() as $key => $value)         
            $results[] = self::getWorkanaInfo($client, $value["id"], $value["user_id"], $value["url"], json_decode($value["proyects_data"], true));

        return $results;
    }

    public function getWorkanaInfo($client, $alertID, $userID, $url, $proyectsData)
    {

        $workanaPage = $client->request('GET', $url);

        $results = [];
        
        $results =  $workanaPage->filter('#projects .project-item')->each(function (Crawler $node, $i) {

                        $data = [];
                        $data["title"]             = $node->filter('.project-title span')->extract(['title'])[0];
                        $data["published"]         = $node->filter('.date')->text();
                try {   $data["deadline"]          = $node->filter('.deadline .value')->text(); } catch (\Throwable $th) { $data["deadline"] = null; }
                        $data["bids"]              = $node->filter('.bids')->text();
                        $data["price"]             = $node->filter('.values')->text();
                        $data["country"]           = $node->filter('.country-name')->text();
                        $data["link"]              = "https://workana.com".$node->filter('.project-title a')->extract(['href'])[0];
                        $data["description"]       = $node->filter('.expander')->text();

                        return $data;

                    });

        $newProyects = self::compareData($results, $proyectsData);
        
        if($newProyects){
            foreach($newProyects as $newProyect) 
                self::sendMessage($client, $newProyect, $userID);
        }

        Proyect::find($alertID)->update(["proyects_data" => $results]);

        return ["ID" => $alertID, "status" => "ok"];
    }


    public function compareData($data, $proyects){

        $newProyect=true;
        $newProyectData = [];
        
        foreach ($data as $key => $value) {
            $newProyect=true;

            if($proyects){
                foreach ($proyects as $key2 => $value2) {
                    if($value["title"]==$value2["title"]){
                        $newProyect=false;
                    }

                }
            }
            if($newProyect && ($value["published"]=="Hace instantes" || $value["published"]=="Just now")){
                $newProyectData[$key] = $value;
            }
        }

        return $newProyectData;

    }


    public function sendMessage($client, $data, $IDUser){


                $message = <<<TEXT
                    ------- <b>¡NUEVO PROYECTO PUBLICADO!</b> -------
                    <b>Titulo:</b> $data[title];
                    <b>Publicado:</b> $data[published];
                    <b>Plazo:</b> $data[deadline];
                    <b>Propuestas:</b> $data[bids];
                    <b>Presupuesto:</b> $data[price];
                    <b>Pais:</b> $data[country];
                    <b>link:</b> $data[link];
                    <b>Descripcion:</b> $data[description];
                    ---------------------------------------------------------------
                    TEXT;
                    $message = urlencode($message);
  
        try {
            $client->request('GET', 'https://api.telegram.org/'.\Config::get('app.bot_api_key').'/sendMessage?chat_id='.$IDUser.'&parse_mode=HTML&text='.$message);
        } catch (\Throwable $th) {
            return $th;
        }

        return "ok";
    }


    public function getProfile(){


        $jar = \GuzzleHttp\Cookie\CookieJar::fromArray(
            [
                "appcookie[activeSession]"               => "1",
                "appcookie[campaign_landing_id]"         => "35254197",
                "appcookie[last_campaign_landing_id]"    => "38243478",
                "appcookie[user_interests]"              => "2MBkkNBxEEqbIiboUSrgYF6WONZajh",
                "appcookie[user_locale]"                 => "es_AR",
                "appcookie[wd]"                          => "4LgVdqHHiMpx3eY33NtxjSYbj7Op19d087RumiTj",
                "workana_session"                        => "k277k44bdkdiob8v4i8juuodn5",

            ],

            "www.workana.com"
        );

        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', 'https://www.workana.com/messages/bid/free-fire-sin-internet-gratis', [
            'cookies' =>$jar,
        ]);


        //  return $response->getBody()->getContents();

        $crawler = new Crawler($response->getBody()->getContents());
        
        $formData=[];

        $data =  $crawler->filter('#add-message question')->extract([":initials"]);
        $data = json_decode($data[0], true);
        
        $urlToSend                              =  $data["url"];
        $formData['csrf_name']                  =  $crawler->filter('input[name=csrf_name]')->extract(['value'])[0];
        $formData['csrf_value']                 =  $crawler->filter('input[name=csrf_value]')->extract(['value'])[0];
        $formData['params']                     =  json_encode($data["attachmentsGallery"]["transloadit"]["params"]);
        $formData['signature']                  =  json_encode($data["attachmentsGallery"]["transloadit"]["signature"]);
        $formData['_upload']                    =  '{"wait":true,"processZeroFiles":false}';
        $formData["first_message"]              =  true;
        $formData["message[attachments[]]"]     =  null;

        $formData["message"] = <<<EOF
            Hola! Mi nombre es José Andrés, soy programador  freelancer apasionado por el desarrollo de soluciones tecnológicas para
            startups, pymes y otras organizaciones. En mi carrera he trabajado para CANTV, una de las empresas de telecomunicaciones mas
            importantes de mi país en el departamento de sistemas como desarrollador backend, también trabaje en una compañía
            multinacional llamada TechLatam prestando servicio en el departamento de desarrollo frontend, asi que como podrás notar
            puedo abarcar amplios campos de desarrollo en sitios web y aplicaciones.
            Desde hace algún tiempo me he dedicado al trabajo freelance y he afinado mis metodologías de trabajo. Estoy especializado en
            lenguajes como php, javascript, jquery, ruby, python; en frameworks como Laravel y vue; Gestores de contenidos como Joomla y 
            wordpress; Herramientas que mejoran la metodología de desarrollo como nodejs, npm, gulp, jquery, webpack, git, react, 
            composer y también con una infinidad de librerias y plugins. Soy tambien especialista la deteccion y correccion de errores del 
            servidor :)
            
            Soy una persona amigable, completamente autodidacta y siempre abierto a nuevas ideas. Me encanta conversar con mis 
            clientes, siempre he pensado que sus necesidades son mi prioridad. Una de mis mas importantes prioridades a la hora de 
            concretar un proyecto es la buena comunicación con mi cliente, por eso cada día me esfuerzo en poder pensar como ellos 
            piensan para tener una idea clara de lo que quieren.
            
            Te invito a que puedas darle un vistazo a mi perfil para que veas mis calificaciones y los trabajos que he tenido dentro de la 
            plataforma :)
            
            Aqui te dejo algunos de mis proyectos:
            
            artnevents.damplix.com	 
            avicola.damplix.com	 
            bestmusic.damplix.com	 
            cenem.damplix.com	 
            clerckcontable.damplix.com	 
            cronometraje.damplix.com	
            damplix.interline.com.ve	 
            diagnostico.damplix.com	 
            luzzidigital.damplix.com	 
            persimas.damplix.com
            
            Para mí será un honor trabajar en lo que necesitas, cubriendo cada detalle del trabajo como si fuera para mí. Puedo asesorarte 
            en todo lo que tienes dudas, y puedo plasmar tus ideas en proyectos realistas para que le saques todo el provecho que necesitas 
            :)
        EOF;


        // $formData = json_encode($formData);
            
        // return $urlToSend;

        // "appcookie[activeSession]=1;appcookie[campaign_landing_id]=35254197;appcookie[last_campaign_landing_id]=38243478;appcookie[user_interests]=2MBkkNBxEEqbIiboUSrgYF6WONZajh;appcookie[user_locale]=es_AR;appcookie[wd]=4LgVdqHHiMpx3eY33NtxjSYbj7Op19d087RumiTj;workana_session=k277k44bdkdiob8v4i8juuodn5",
        
        try {
            $response = $client->request('POST', $urlToSend, [
                'cookies'        => $jar,
                'form_params'    => $formData,
                'headers'        => [

                    ":authority"                     => "www.workana.com",
                    ":method"                       => "POST",
                    ":path"                         => explode("https://www.workana.com/", $urlToSend)[1],
                    ":scheme"                       => "https",
                    "accept"                        => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
                    "accept-encoding"               => "gzip, deflate, br",
                    "accept-language"               => "es-ES,es;q=0.9",
                    "cache-control"                 => "no-cache",
                    "content-type"                  => "application/x-www-form-urlencoded",
                    "cookie"                        => "appcookie[user_locale]=es_AR; appcookie[campaign_landing_id]=35254197; appcookie[wd]=4LgVdqHHiMpx3eY33NtxjSYbj7Op19d087RumiTj; appcookie[user_interests]=2MBkkNBxEEqbIiboUSrgYF6WONZajh; appcookie[last_campaign_landing_id]=38243478; appcookie[activeSession]=1; workana_session=k277k44bdkdiob8v4i8juuodn5",
                    "origin"                        => "https://www.workana.com",
                    "pragma"                        => "no-cache",
                    // "referer"                       => "https://www.workana.com/messages/bid/realizar-tareas-de-programacion/?tab=message&ref=project_view",
                    "sec-fetch-dest"                => "document",
                    "sec-fetch-mode"                => "navigate",
                    "sec-fetch-site"                => "same-origin",
                    "sec-fetch-user"                => "?1",
                    "upgrade-insecure-requests"     => "1",
                    "user-agent"                    => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36"
                ],
            ]);

        } catch (\Throwable $th) {
            dump($th->getMessage());
        }


         return dump($response);
        

    }


    public function setConfig(Request $request)
    {
        

        $client = new \GuzzleHttp\Client();
            
        $user=$request->message["from"]["id"];

        switch (explode(" ", $request->message["text"])[0]) {
            case "/createalert":
                $message = self::createAlert($request);
                break;

            case "/showalerts":
                $message = self::showAlerts($request);
                break;
            
            case "/deletealert":
                $message = self::deleteAlert($request);
                break;        

            case "/disablealert":
                $message = self::disableAlert($request);
                break;  
            case "/enablealert":
                $message = self::enableAlert($request);
                break;  
            default:
                $message = "Lo siento, no te entiendo :(";
                break;
        }

        $client->request('GET', 'https://api.telegram.org/'.\Config::get('app.bot_api_key').'/sendMessage?chat_id='.$user.'&parse_mode=HTML&text='.$message);
     

        return (new Response("Success", 200));
    }

     public function disableAlert(Request $request)
     {
        if(!Proyect::find(explode(" ", $request->message["text"])[1])){
            return "Lo siento, no tienes ninguna alerta con la ID que has enviado";
        }
         Proyect::where("id", explode(" ", $request->message["text"])[1])->update(["active" => 0]);

         return "Alerta desactivada exitosamente";
     }   

    public function enableAlert(Request $request)
    {
        if(!Proyect::find(explode(" ", $request->message["text"])[1])){
            return "Lo siento, no tienes ninguna alerta con la ID que has enviado";
        }
        Proyect::where("id", explode(" ", $request->message["text"])[1])->update(["active" => 1]);

        return "Alerta activada exitosamente";
    }

    public function createAlert(Request $request)
    {

        Proyect::insert([
            "user_id" => $request->message["from"]["id"],
            "url"     => explode(" ", $request->message["text"])[1]
        ]);

        return "¡Tu alerta ha sido creada correctamente!";
    }

    public function deleteAlert(Request $request){

        if(!Proyect::find(explode(" ", $request->message["text"])[1])){
            return "Lo siento, no tienes ninguna alerta con la ID que has enviado";
        }

        Proyect::where("id", explode(" ", $request->message["text"])[1])->delete();
        return "Alerta Eliminada exitosamente";
    
    }

    public function showAlerts(Request $request)
    {
        $proyects = Proyect::where("user_id", $request->message["from"]["id"])->get()->toArray();
        
        $messageContent = '';
        foreach ($proyects as $key => $value) {

            $active = $value["active"]==0 ? "Desactivada" : "Activada";

            $messageContent = $messageContent . <<<TEXT
                                                ID: $value[id]
                                                URL: $value[url]
                                                ESTADO: $active
                                                --------------------------
                                                --------------------------
                                                TEXT;
        }

        $message = <<<TEXT
                    <b>Alertas Registradas</b>
                    $messageContent
                    TEXT;
                    $message = urlencode($message);

        return $message;
    }


}