* Set up the VM as in other user stories
* On the VM console go to /opt/microservices/pdx and add run
  ```
  git remote add acdh https://github.com/nczirjak-acdh/pdx.git
  git pull -s recursive -X theirs acdh master
  composer update
  ```
* Prepare a simple data input form allowing you to query the middleware service and open it in a browser, e.g.:
  ```
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
  <meta charset="utf-8">
  <script
			  src="https://code.jquery.com/jquery-2.2.4.min.js"
			  integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44="
			  crossorigin="anonymous"></script>
  <script type="text/javascript">
    $().ready(function(){
        $('#form').submit(function(){
            var form = document.getElementById('form');
            var request = new XMLHttpRequest();
            var fd = new FormData(form);
            request.onload = function(e){
                console.log(req);
            };
            request.open("POST", "http://localhost:8282/islandora/acdh/resourceName");
            request.send(fd);
        });
    });
  </script>
</head>
<body>
<form action="#" onsubmit="return false;" id="form">
    <input type="hidden" name="MAX_FILE_SIZE" value="3000000" />
    file: <input name="file" type="file" /><br/>
    prop1: <input type="text" name="prop1"/><br/>
    prop2: <input type="text" name="prop2"/><br/>
    prop3: <input type="text" name="prop3"/><br/>
    prop4: <input type="text" name="prop4"/><br/>
    prop5: <input type="text" name="prop5"/><br/>
    <input type="submit" value="Send" />
</form>
</body>
</html>
  ```
* Now you can query the middleware endpoint and create resources through it.
  You can track query results using develoment console of your browser.
  You can view created resources in the Fedora WWW GUI at http://localhost:8080/fcrepo/rest
  You can also view resources metadata using triplestore WWW GUI at http://localhost:8080/bigdata
