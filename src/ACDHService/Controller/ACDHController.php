<?php

namespace Islandora\PDX\ACDHService\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\File;
use Islandora\Chullo\Uuid\IUuidGenerator;
use Islandora\Chullo\Chullo;
use Islandora\Chullo\TriplestoreClient;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use ML\JsonLd\JsonLd;

class ACDHController
{

    protected $uuidGenerator;

    public function __construct(Application $app, IUuidGenerator $uuidGenerator)
    {
        $this->uuidGenerator = $uuidGenerator;
    }
    
    /*
     *  Drupal Form Data - metakey processing
     * 
     *  We are saving only those keys which are have values
     */
    public function processFormValues($data)
    {        
        if(empty($data) && !isset($data)) { return false; }
        
        $keys = array();
        
        foreach ($data as $key => $value)
        {            
            if(!empty($value))
            {
                $keys[] = $key;            
            }
        }        
        return $keys;                
    }
    
    
    public function metaKey1()
    {
        $data = array("test1", "http://aaaa_bbb/ccc", "test2", "test3", "Slug");
        
        return $data;
    }
    
    public function metaKey2()
    {
        $data = array("test1", "http://aaaa_bbb/ccc", "test2", "Slug");            
        
        return $data;
    }
    
    public function metaKey3()
    {
        $data = array("test1", "Slug");        
        
        return $data;
    }


    public function getSlugData($data)
    {
        if(empty($data) && !isset($data)) { return false; }
                        
        foreach ($data as $key => $value)
        {            
            if($key == "Slug") { $slugValue = $value; }
        }
        
        return $slugValue;       
        
    }
    
    
    /*
     * This will get the contentType metafields from Fedora4, based on the ContentType
     */
    
    public function checkMetaKeys($formKeys)
    {
        /* two content type for example */
        /* Symphony replace the . to _ */
        
        if(empty($formKeys) && !isset($formKeys))
        {
            return false;
        }
        $metaArray = array();
        
        if(!empty($this->metaKey1()))
        {
            $metaArray['meta1'] = $this->metaKey1();
        }
        
        if(!empty($this->metaKey2()))
        {
            $metaArray['meta2'] = $this->metaKey2();
        }
        
        if(!empty($this->metaKey3()))
        {
            $metaArray['meta3'] = $this->metaKey3();
        }
        
        foreach($metaArray as $key => $value)
        {
            /*
             *  if there is no difference between the formkeys and the metakeys 
             *  then we creating metadiff arrays with the fields
             */
            
            if(empty(array_diff($value, $formKeys)))
            {                
                $metaDiff[$key] = $metaArray[$key];
            }
        }       
        
        return $metaDiff;
    }
    
   
    public function create(Application $app, Request $request, $id)
    {
        
        $uplFileTMPPath = $request->files->get('fileToUpload')->getPathName();
        $uplFileName = $request->files->get('fileToUpload')->getFileName();
        //$uplFileMime = $request->files->get('fileToUpload')->getMimeType(); 
        $uplFileMime = $request->files->get('fileToUpload')->getClientMimeType();
        
        $uplFileContent = file_get_contents($uplFileTMPPath);            
        
        // Instantiated with static factory
        $chullo = Chullo::create("http://localhost:8080/fcrepo/rest");
        
        
        // Create a new resource
        $uri = $chullo->createResource(); // http://localhost:8080/fcrepo/rest/0b/0b/6c/68/0b0b6c68-30d8-410c-8a0e-154d0fd4ca20
        
        // Parse resource as an EasyRdf Graph
        $graph = $chullo->getGraph($uri);
        
        // Set the resource’s title
        $graph->set($uri, 'dc:title', 'My Sweet Title');

        // Save the graph to Fedora
        $chullo->saveGraph($uri, $graph);

        // Open a transaction
        $transaction = $chullo->createTransaction(); //tx:2b27e944-483d-4e59-a33b-f378bd42faf5
        
        $content = $uplFileContent;
        
        $child_uri = $chullo->createResource(
        //$child_uri = $chullo->saveResource(
            $uri,            
            $content,            
             ['Content-Type' => 'text/xml'],
            $transaction,
            sha1($content)
            );
      
        // Commit it
        $chullo->commitTransaction($transaction);

        // Check it out:
        echo $uri . "\n";
        echo "child \n";
        var_dump(print_r($child_uri, true));
    }

    public function createCurl(Application $app, Request $request, $id)
    {
        /* get the uploaded file infos */
        $uplFileName = $request->files->get('fileToUpload')->getClientOriginalName();
        $uplFileTMPPath = $request->files->get('fileToUpload')->getPathName();
        $uplFileName = $request->files->get('fileToUpload')->getFileName();
        //$uplFileMime = $request->files->get('fileToUpload')->getMimeType(); 
        $uplFileMime = $request->files->get('fileToUpload')->getClientMimeType();
        
        $uplFileContent = file_get_contents($uplFileTMPPath);            
        /* if the uploaded file is empty */
        if(empty($uplFileContent))
        {
            return false;
        }   
        
        /*
        * THE META DATA CHECKING PART
        */        
        $objFormVal = $request->request;        
        // get the formkeys
        $formKeys = $this->processFormValues($objFormVal);
        
        $slugValue = $this->getSlugData($objFormVal);

        //if not empty the form or meta array then compare them
        if(!empty($formKeys) && isset($formKeys)) 
        {                                
            $metaKeys = $this->checkMetaKeys($formKeys);            
                        
            if(empty($metaKeys))
            {
                return false;
            }
            
        }
        else
        {
           die("Missing data");
        }           
       
        /* file inserting */
        $headers = array('Content-Type: '.$uplFileMime);    
        $post = array('file_contents'=>$uplFileContent);        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://localhost:8080/fcrepo/rest/");
        curl_setopt($ch, CURLOPT_POST,1);        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);    
        $result=curl_exec ($ch);
        curl_close ($ch);
        
        $CollectionURLMeta = $result.'/fcr:metadata';
        $CollectionURL = $result;
                
        
        /* creating the sparql -  this section is not completed */
        $dataStringLine = "";
        var_dump("the usable content types: ");
        foreach($metaKeys as $key => $values)
        {      
            
            var_dump($key);
            var_dump("<br>");
            
            foreach($values as $value )
            {        
                $dataStringLine .="<$CollectionURL> acdh:$value   <http://127.0.0.1:8080/fcrepo/rest/TEI> . ";                                
            }                            
        }
                
        $data_string = "PREFIX acdh: <http://acdh.oeaw.ac.at/#>
                                INSERT {
                                  
                                     <$CollectionURL> acdh:usesXSLT   <http://127.0.0.1:8080/fcrepo/rest/ContentType/TEI/XSLT> .
                                     <$CollectionURL> acdh:providedBy <http://127.0.0.1/xsltProcessor.php> .
                                }
                                WHERE {}";
        
        
        $headers = array('Content-Type: application/sparql-update');
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $CollectionURLMeta);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch2, CURLOPT_POST,1);
        curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
   
        // execute the request
        $result2=curl_exec ($ch2);        
        curl_close ($ch2);
  
        var_dump("File uploading: ");
        var_dump($result);
        var_dump("<br><br><br>");
        var_dump("SPARQL query: ");
        var_dump($result2);
        
        die("END");
        

    }
   
    
    /**
     * Add a proxy object to the collection for the member object.
     *
     * @param Application $app
     *   The silex application.
     * @param Request     $request
     *   The Symfony request.
     * @param string      $id
     *   The UUID of the collection.
     * @param string      $member
     *   The UUID of the object to add to the collection.
     */
    public function addMember(Application $app, Request $request, $id, $member)
    {
        $tx = $request->query->get('tx', "");

        $urlRoute = $request->getUriForPath('/islandora/resource/');

        $members_uri = $app['islandora.idToUri']($member);
        if (is_a($members_uri, 'Symfony\Component\HttpFoundation\Response')) {
            return $members_uri;
        }

        $members_proxy_rdf = $app['twig']->render(
            'createOreProxy.json',
            array(
            'resource' => $members_uri,
            )
        );

        $fullUri = $app['islandora.idToUri']($id);
        if (is_a($fullUri, 'Symfony\Component\HttpFoundation\Response')) {
            return $fullUri;
        }

        $fullUri .=  '/members';

        $newRequest = Request::create(
            $urlRoute . $id . '/members-add/' . $member,
            'POST',
            array(),
            $request->cookies->all(),
            array(),
            $request->server->all(),
            $members_proxy_rdf
        );
        $newRequest->headers->set('Content-type', 'application/ld+json');
        $newRequest->headers->set('Content-Length', strlen($members_proxy_rdf));
        $response = $app['islandora.resourcecontroller']->post($app, $newRequest, $fullUri);
        if (201 == $response->getStatusCode()) {
            return new Response($response->getBody(), 201, $response->getHeaders());
        }
        //Abort if PCDM collection object could not be created
        return $response;
        //return new Response($response->getStatusCode(), 'Failed adding member to PCDM Collection');

    }
    
    
    /**
     * Remove the proxy object for the member from the collection.
     *
     * @param Application $app
     *   The silex application.
     * @param Request     $request
     *   The Symfony request.
     * @param string      $id
     *   The UUID of the collection.
     * @param string      $member
     *   The UUID of the object to remove from the collection.
     */
    public function removeMember(Application $app, Request $request, $id, $member)
    {
        $tx = $request->query->get('tx', "");
        $force = $request->query->get('force', 'FALSE');

        $urlRoute = $request->getUriForPath('/islandora/resource/');

        $collection_uri = $app['islandora.idToUri']($id);
        if (is_object($collection_uri)) {
            return $collection_uri;
        }

        $member_uri = $app['islandora.idToUri']($member);
        if (is_object($member_uri)) {
            return $member_uri;
        }

        $sparql_query = $app['twig']->render(
            'findOreProxy.sparql',
            array(
              'collection_member' =>  $collection_uri . '/members',
              'resource' => $member_uri,
            )
        );
        try {
            $sparql_result = $app['triplestore']->query($sparql_query);
            
        } catch (\Exception $e) {
            $app->abort(503, 'Chullo says "Triple Store Not available"');
        }
        if (count($sparql_result) > 0) {
            $existing_transaction = (!empty($tx));
            $newRequest = Request::create(
                $urlRoute . 'dummy/delete',
                'POST',
                array(),
                $request->cookies->all(),
                array(),
                $request->server->all()
            );
            if (!$existing_transaction) {
                // If we don't have a transaction create one here
                $response = $app['islandora.transactioncontroller']->create($app, $newRequest);
                $tx = $app['islandora.transactioncontroller']->getId($response);
            }
            $newRequest = Request::create(
                $urlRoute . 'dummy/delete?force=' . $force . '&tx=' . $tx,
                'DELETE',
                array(),
                $request->cookies->all(),
                array(),
                $request->server->all()
            );
            foreach ($sparql_result as $triple) {
                $response = $app['islandora.resourcecontroller']->delete($app, $newRequest, $triple->obj->getUri(), "");
                if (204 != $response->getStatusCode()) {
                    $app->abort($response->getStatusCode(), 'Error deleting object');
                }
            }
            if (!$existing_transaction) {
                // If this is not a passed in transaction, then we can commit it here.
                $response = $app['islandora.transactioncontroller']->commit($app, $newRequest, $tx);
                if (204 == $response->getStatusCode()) {
                    return $response;
                } else {
                    $app->abort($response->getStatusCode(), 'Failed removing member from PCDM Collection');
                }
            }
        }
        // Not sure what to return if the transaction hasn't been committed yet.
        return Response::create("", 204, array());
    }
}
