<?php

namespace Islandora\PDX\ACDHService\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\File;
use Islandora\Chullo\Uuid\IUuidGenerator;
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
     */
    public function processFormValues($data)
    {
        
        if(empty($data) && !isset($data)) { return false; }
        
        $keys = array();
        
        foreach ($data as $key => $value)
        {            
            $keys[] = $key;            
        }
        
        return $keys;                
    }
    
    /*
     * This will get the contentType metafields from Fedora4, based on the ContentType
     */
    
    public function metaKeys($contentType = null)
    {
        /* two content type for example */
        /* Symphony replace the . to _ */
        $data = array();
        
        if($contentType == 'XML')
        {
            $data = array("asd", "http://aaaa_bbb/ccc", "age", "asd");            
        }
        
        if($contentType == 'XML2')
        {
            $data = array("asd2", "http://aaaa_bbb/ccc2", "age2", "asd");            
        }
        
        if($contentType == 'XML3')
        {
            $data = array("asd");            
        }
        
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

    public function create(Application $app, Request $request, $id)
    {
        
        $uplFileName = $request->files->get('fileToUpload')->getClientOriginalName();
        $uplFileTMPPath = $request->files->get('fileToUpload')->getPathName();
        $uplFileName = $request->files->get('fileToUpload')->getFileName();
        //$uplFileMime = $request->files->get('fileToUpload')->getMimeType(); 
        $uplFileMime = $request->files->get('fileToUpload')->getClientMimeType();
        
        $uplFileContent = file_get_contents($uplFileTMPPath);            
        
        if(empty($uplFileContent))
        {
            return false;
        }       
        
        
        /*
        * 
        * THE META DATA CHECKING PART
        * 
        */        
        $objFormVal = $request->request;        
        // get the formkeys
        $formKeys = $this->processFormValues($objFormVal);
        //get the schema metakeys
        $metaKeys = $this->metaKeys();

        $slugValue = $this->getSlugData($objFormVal);
/*
        //if not empty the form or meta array then compare them
        if((!empty($formKeys) && isset($formKeys)) 
               && (!empty($metaKeys) && isset($metaKeys)) 
         )
        {              
           $metaDiff = array_diff($metaKeys, $formKeys);                                      
           /* here i need to add the sparql query for get the existing collections 
            *  and then i can compare and select to which collections can use the uploaded file
            */            
  /*      }
        else
        {
           die("Missing data");
        }           
    */   
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
                
        /* sparql update a form adtaok alapjan */
        
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
  
        var_dump("result 1: ");
        var_dump($result);
        var_dump("<br><br><br>");
        var_dump("URL META: ");
        var_dump($CollectionURLMeta);
        var_dump("<br> MIME: ");
        var_dump($uplFileMime);
        var_dump("<br><br><br>");
        var_dump("result 2: ");
        var_dump($result2);
        die("ittt2");
        

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
