<?php

//Importing data files section start

    $fl=fopen("data/testing_data_kolkata.csv","r");//Data file with active infected cases
    $cordinatesArr=[];
    $data=fgetcsv($fl,0,"\n");
    while(($data=fgetcsv($fl,0,"\n"))!==FALSE)//Taking each line at a time
    {
        $str_data=join("",$data);//Converting to string
        $str_arr = preg_split("/\,/", $str_data);//Splitting the string based on comma
        $lng=(float)$str_arr[0];
        $lat=(float)$str_arr[1];

        $cordinatesArr[]=array($lng,$lat);
    }

    fclose($fl);

    $h=fopen("data/centroids_kolkata.csv","r");//Data file with location of centroids of the clusters
    $centroidsArr=[];
    while(($data=fgetcsv($h,1000,"\n"))!==FALSE)
    {
        $str_data=join("",$data);
        $str_arr = preg_split("/\,/", $str_data);
        $lng=(float)$str_arr[0];
        $lat=(float)$str_arr[1];

        $centroidsArr[]=array($lng,$lat);
    }

    fclose($h);

    $f=fopen("data/corresponding_clusters_kolkata.csv","r");//Data file with cluster no. corresponding to each active case
    $clustersArr=[];
    while(($data=fgetcsv($f,1000,"\n"))!==FALSE)
    {
        $str_data=join("",$data);
        $str_arr = preg_split("/\,/", $str_data);
        $lng=(float)$str_arr[0];
        $lat=(float)$str_arr[1];
        $clusterNo=(int)$str_arr[2];
        $clusterArr[]=array($lng,$lat,$clusterNo);
    }

    fclose($f);

    $g=fopen("data/boundary_kolkata.csv","r");//Data file with boundary points of each of the clusters
    $boundaryArr=[];
    $pattern = "/[,\"\s@]/";
    $data=fgetcsv($g,1000,"\n");
    while(($data=fgetcsv($g,1000,"\n"))!==FALSE)
    {
        $str_data=join("",$data);//Here each string contains all the boundary points of a cluster
        $str_data=str_replace(array("[","]"), "@", $str_data);
        
        $str_arr = preg_split($pattern, $str_data);//Splitting the string based on the pattern in the data file
        $correspondingCluster=(float)$str_arr[0];

        $pts=[];$flag=0;
        
        for ($i=1; $i <count($str_arr) ; $i++) 
        { //Storing the boundary points of each of the clusters in an array

            if($str_arr[$i]!="")
            {
              if($flag==0)
              {
                $lng=(float)$str_arr[$i];
                $flag=1;
              }
              else
              {
                $pts[]=array($lng,(float)$str_arr[$i]);
                $flag=0;
              }
              
            }
        }
        
        $boundaryArr[]=$pts;// $boundaryArr is an array of array. It stores the boundaries of all the clusters.
    }

    fclose($g);

//Importing data files section end


//Routes generation section start

    if(isset($_POST['submitbtn']))
    {
      //Getting a route from source to destination using google maps api
      $jsondata=file_get_contents("https://maps.googleapis.com/maps/api/directions/json?origin=".urlencode($_POST['source'])."&destination=".urlencode($_POST['destination'])."&key=API_KEY");
      
      if($jsondata!=NULL)
      {
        $data=json_decode($jsondata,true);
        $stcor=array($data['routes'][0]['legs'][0]['start_location']);//Source co-ordinate

        $lines=array();
        foreach($data['routes'][0]['legs'] as  $key=>$val)
        {
          foreach($val['steps'] as $step_key=>$step_val)
          {
            array_push($lines, ($step_val['polyline']['points']));//Storing all the polylines obtained from the API call
                                                                  //in an array.

            $endcor=$step_val['end_location'];//Destination co-ordinate
          }
        }
        
        $allines=array($lines);
        $slat=$stcor[0]['lat'];
        $slng=$stcor[0]['lng'];
        
        $endlat=$endcor['lat'];
        $endlng=$endcor['lng'];


        $s=(($slat-$endlat)/($slng-$endlng));// Slope
        $xc=($slng+$endlng)/2;// Centre of the ellipse x-cord
        $yc=($slat+$endlat)/2;// Centre of the ellipse y-cord


        //Tranformed source co-ordinate
        $sx_tranformed=(($slng-$xc)+($slat-$yc)*$s)/(sqrt(1+$s*$s));
        $sy_tranformed=(($slat-$yc)-($slng-$xc)*$s)/(sqrt(1+$s*$s));


        //Transformed destination co-ordinate
        $dx_tranformed=(($endlng-$xc)+($endlat-$yc)*$s)/(sqrt(1+$s*$s));
        $dy_tranformed=(($endlat-$yc)-($endlng-$xc)*$s)/(sqrt(1+$s*$s));


        $a=sqrt((($sx_tranformed-$dx_tranformed)*($sx_tranformed-$dx_tranformed))+(($sy_tranformed-$dy_tranformed)*($sy_tranformed-$dy_tranformed)));// Major Axis
        $b=(2/3)*$a;// Minor Axis
        

        $mul=1000000;//Needed for generating random waypoints
        $sd=0;       //Needed for generating random waypoints

        $latitude=array(); //For storing latitude & longitude of the waypoints
        $longitude=array();


        for($no=0;$no<3;$no++)
        {
          $waypts_lat=array();
          $waypts_lng=array();

          for($i=1;$i<4;$i++)
          { //Creating a random way point

            mt_srand(27*$i+$sd);//Giving a seed value

            if($sx_tranformed<$dx_tranformed)
            {
              $x=mt_rand($sx_tranformed*$mul,$dx_tranformed*$mul)/$mul;
            }
            else
              $x=mt_rand($dx_tranformed*$mul,$sx_tranformed*$mul)/$mul;

            $y_upper=$b*(sqrt(1-(($x*$x)/($a*$a))));
            $y_lower=(-1)*$y_upper;

            $y=mt_rand($y_lower*$mul,$y_upper*$mul)/$mul;

            $x_tranform=((($x)-($y)*$s)/(sqrt(1+$s*$s)))+$xc;
            $y_tranform=((($y)+($x)*$s)/(sqrt(1+$s*$s)))+$yc;

            array_push($waypts_lng, $x_tranform);
            array_push($waypts_lat, $y_tranform);
          }

          $sd+=(29+($no*20));
          

          //Requesting the route from source to destination with the waypoints
          $jsondata=file_get_contents("https://maps.googleapis.com/maps/api/directions/json?origin=".urlencode($_POST['source'])."&destination=".urlencode($_POST['destination'])."&waypoints=optimize:true%7C".($waypts_lat[0])."%2C".($waypts_lng[0])."%7C".($waypts_lat[1])."%2C".($waypts_lng[1])."%7C".($waypts_lat[2])."%2C".($waypts_lng[2])."&key=API_KEY");

          foreach($waypts_lng as $key=>$val)
          {
              array_push($longitude, $val);
          }

          foreach($waypts_lat as $key=>$val)
          {
              array_push($latitude, $val);
          }


          if($jsondata!=NULL)
          {
            $data=json_decode($jsondata,true);
            
            if($data['status']=='OK')
            {
              $lines=array();
              foreach($data['routes'][0]['legs'] as  $key=>$val)
              {
                
                foreach($val['steps'] as $step_key=>$step_val)
                {
                  array_push($lines, ($step_val['polyline']['points']));//Storing all the polylines obtained from the API call
                                                                        //in an array.
                }
              }

              array_push($allines, $lines);
            }
            else
            {
              $no--; //If no route found with the specified waypoints. Ignore them
                     //and generate another set of waypoints.
            }
          }

        }
      }

    }

//Routes generation section end

?>


<!DOCTYPE html>

  <html>

    <head>

      
      <!--Bootstrap file-->    
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

      <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
      
      <!--JQuery file-->
        <script type="text/javascript" src="jqueryfile.min.js"></script>
      <script src="jquery-ui/jquery-ui.js"></script>
      <link rel="stylesheet" href="jquery-ui/jquery-ui.min.css">


      
      <title>Maps API</title>
      <!--Adding scripts for using maps api-->
      <script src="https://polyfill.io/v3/polyfill.min.js?features=default"></script>
      <script
        src="https://maps.googleapis.com/maps/api/js?key=API_KEY&callback=initMap&libraries=&v=weekly"
        defer
      ></script>
      <script src="https://maps.googleapis.com/maps/api/js?key=API_KEY&libraries=geometry"></script>
      <script defer src="https://maps.googleapis.com/maps/api/js?key=API_KEY&libraries=visualization&callback=initMap"></script>
      
      <!--A little bit CSS-->
      <style type="text/css">
        #map {
          height: 100%;
        }

        html,
        body {
          background-image: url("backgroundimg.jpg");
          height: 100%;
          background-size: cover;
          margin: 0;
          padding: 0;
        }

        div:empty {
          display:none;
        }

      </style>
      <script>
        "use strict";


        function euclidean(lon1,lat1,lon2,lat2) {
            var ret=Math.sqrt((lon1-lon2)*(lon1-lon2)+(lat1-lat2)*(lat1-lat2));
            return ret;
        }

        function degreesToRadians(degrees) {
            return degrees * Math.PI / 180;
        }

        //Haversine distance in km
        function haversine(lat1, lon1, lat2, lon2) {
          var earthRadiusKm = 6371;

          var dLat = degreesToRadians(lat2-lat1);
          var dLon = degreesToRadians(lon2-lon1);

          lat1 = degreesToRadians(lat1);
          lat2 = degreesToRadians(lat2);

          var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.sin(dLon/2) * Math.sin(dLon/2) * Math.cos(lat1) * Math.cos(lat2); 
          var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
          return earthRadiusKm * c;
        }

//Data & Cluster circle section start

        var cordinatesData=<?php echo(json_encode($cordinatesArr)) ?>;//Importing data from php to js
        var boundaryData=<?php echo(json_encode($boundaryArr)) ?>;
        var centroidsData=<?php echo(json_encode($centroidsArr)) ?>;
        var clusterData=<?php echo(json_encode($clusterArr)) ?>;


        var noCluster=centroidsData.length;
        var pointsClusterWise=new Array(noCluster);
        var pointsInEachCluster=new Array(noCluster);

        for(var i=0;i<noCluster;i++)
        {
            pointsClusterWise[i]=[];
        }

        for(var i=0;i<clusterData.length;i++)
        { //Storing the points cluster wise
            pointsClusterWise[clusterData[i][2]].push({lng:clusterData[i][0],lat:clusterData[i][1]});
        }

        for(var i=0;i<noCluster;i++)
        {
            pointsInEachCluster[i]=pointsClusterWise[i].length;
        }

        var radius=new Array(noCluster);
        for(var i=0;i<centroidsData.length;i++)
        {
            radius[i]=0;
            for(var j=0;j<pointsClusterWise[i].length;j++)
            {
              //Max radius
              radius[i]=Math.max(radius[i],euclidean(centroidsData[i][0],centroidsData[i][1],pointsClusterWise[i][j]['lng'],pointsClusterWise[i][j]['lat']));
              //Average radius
              //radius[i]+=euclidean(centroidsData[i][0],centroidsData[i][1],pointsClusterWise[i][j]['lng'],pointsClusterWise[i][j]['lat']);
            }
            //For average radius
            //radius[i]=(radius[i]/(pointsClusterWise[i].length));
        }
        
//Data & Cluster circle section end


//Routes and Heatmap section start

    	  const thelines=<?php echo(json_encode($allines)) ?>;//Fetching all the routes
        var plot_way_pts_lat=<?php echo(json_encode($latitude)) ?>;//Fetching all the waypoints
        var plot_way_pts_lng=<?php echo(json_encode($longitude)) ?>;
        
        var src_lng=<?php echo($slng) ?>;//Co-ordinates of the source
        var src_lat=<?php echo($slat) ?>;
        var dest_lng=<?php echo($endlng) ?>;//Co-ordinates of the destination
        var dest_lat=<?php echo($endlat) ?>;


        var flightPlanCordfn=[];//It is an array of array. It Stores the co-ordinates of all the routes.
        var n=thelines.length;

        for(var lp=0;lp<n;lp++)//Considering one route at a time
        {
        	  var flightPlanCoordinates=[];//A 1D array to store one route.
      		  for(var i=0;i<thelines[lp].length;i++)
      		  {
      		  		const path=google.maps.geometry.encoding.decodePath(thelines[lp][i]);//Decoding the encoded polyline
      		   		for(var x in path)
      		   		{
      		   			  flightPlanCoordinates.push({lat:path[x].lat(),lng:path[x].lng()});//Storing the points obtained
                                                                                      //by decoding the polylines
      		   		}
      		  }

            //Here goes the loop removing
            var dict={};
            var ind1,ind2,lati,longi,flag=0,loop_count=0;
            var s="";
            ind1=-1;
            while(true)//Removing the loops on the route
            {
                flag=0;

                if(loop_count>1000)//A safety measure to restrict infinite loop
                  break;

                for(var x=ind1+1;x<flightPlanCoordinates.length;x++)
                {
                    lati=flightPlanCoordinates[x]['lat'];
                    longi=flightPlanCoordinates[x]['lng'];
                    s="";
                    s=s+lati+","+longi;
                    if(s in dict)//If coordinate is already available in dictionary then a loop is there
                    {
                      ind1=dict[s];
                      ind2=x;
                      flag=1;
                      break;
                    }
                    else
                    {
                      dict[s]=x;
                    }
                }

                if(flag==1)
                {
                  flightPlanCoordinates.splice(ind1,ind2-ind1);//removing the loop here
                }
                else//If no loop is there on the route then break the while loop
                  break;

                loop_count++;//Total loop count on the route
            }

      		  //Storing the route after removing the loops
            flightPlanCordfn.push(flightPlanCoordinates);
        }

        function initMap() {
          //Loading a general road map 
          const map = new google.maps.Map(document.getElementById("map"), {
            zoom: 14,
            center: flightPlanCoordinates[0],
            mapTypeId: "roadmap"
          });


          var flightPath = [];
          var clr=["#FF0000","#030894","#000000","#006312","#34e2eb","#9914ff"];//Red,Blue,Black,Green,Orange,Violet
          n=flightPlanCordfn.length;
          for(var i=0;i<n;i++)
          {
              //Drawing the routes on the map
              flightPath.push(new google.maps.Polyline({
                map:map,
                path: flightPlanCordfn[i],
                geodesic: true,
                strokeColor: clr[i],
                strokeOpacity: 1,
                strokeWeight: 5
              }));
          }

          //Generating Heatmap of clusters
          var heatmapData=[];
          for(var i=0;i<cordinatesData.length;i++)
          {
              heatmapData.push(new google.maps.LatLng(cordinatesData[i][1],cordinatesData[i][0]));
          }

          var heatmap = new google.maps.visualization.HeatmapLayer({ data: heatmapData });
          heatmap.setMap(map);
          
          //For plotting markers of waypoints
          /*var m=centroidsData.length;
          for(var i=0;i<m;i++)
          {
              const myLatLng = { lat: centroidsData[i][1], lng: centroidsData[i][0] };
              const cityCircle = new google.maps.Circle({
              strokeColor: "#000000",
              strokeOpacity: 0.5,
              strokeWeight: 2,
              fillColor: "#000000",
              fillOpacity: 0.35,
              map:map,
              center: myLatLng,
              radius: radius[i]*1000
            });
          }

          var m=plot_way_pts_lat.length;
          for(var i=0;i<m;i++)
          {
              const myLatLng = { lat: plot_way_pts_lat[i], lng: plot_way_pts_lng[i] };
              new google.maps.Marker({position: myLatLng,map,});
          }*/

          const myLatLng= { lat:src_lat, lng:src_lng };
          new google.maps.Marker({position: myLatLng,map,label:"S",});//Source marker

          const myLatLngD= { lat:dest_lat, lng:dest_lng };
          new google.maps.Marker({position: myLatLngD,map,label:"D"});//Destination marker
        
          //Circles corresponding to cluster circle starts

          /*for(var i=0;i<centroidsData.length;i++)
          {
              const cityCircle = new google.maps.Circle({
                strokeColor: "#FF0000",
                strokeOpacity: 0.8,
                strokeWeight: 2,
                fillColor: "#FF0000",
                fillOpacity: 0.35,
                map,
                center: { lat: centroidsData[i][1],lng: centroidsData[i][0] },
                radius: radius[i] * 100000,
              });
          }*/
          
          //Circles corresponding to cluster circle ends

//Routes and Heatmap section start


//Drawing boundary section start
          
          for(var i=0;i<boundaryData.length;i++)
          {
              var boundaryLine=[];
              var len=boundaryData[i].length;
              for(var j=0;j<len;j++)
              {
                  const boundaryLatLng={ lng: boundaryData[i][j][0], lat:boundaryData[i][j][1] };
                  boundaryLine.push(boundaryLatLng);
              }
              const boundaryLatLng={ lng: boundaryData[i][0][0], lat:boundaryData[i][0][1] };
              boundaryLine.push(boundaryLatLng);
              
              
              
              new google.maps.Polyline({
              map:map,
              path: boundaryLine,
              geodesic: true,
              strokeColor: "#000000",
              strokeOpacity: 0.4,
              strokeWeight: 2
            });
            
          }
          
//Drawing boundary section end
        
        } //This brace is the ending of initmap function


//Distance of a route through a cluster calculation section start

        n=flightPlanCordfn.length;//No. of routes
        var x1,x2,y1,y2,xsrc,ysrc,rad,m,f,A,B,C,x,y,root,checkX1,checkX2,checkY1,checkY2;
        var dist=new Array(n);
        var distances_of_path=new Array(n);
        var intersectPts=new Array(n);
        for(var i=0;i<n;i++)
        {
            dist[i]=new Array(noCluster);
            intersectPts[i]=new Array(noCluster);
            for(var k=0;k<noCluster;k++)
            {
                intersectPts[i][k]=[];
            }

            var len_i_th_route=flightPlanCordfn[i].length;
            distances_of_path[i]=0;
            for(var j=1;j<len_i_th_route;j++)
            {
                //Taking two consecutive points
                x1=flightPlanCordfn[i][j-1]['lng'];
                x2=flightPlanCordfn[i][j]['lng'];
                y1=flightPlanCordfn[i][j-1]['lat'];
                y2=flightPlanCordfn[i][j]['lat'];
                distances_of_path[i]+=haversine(y1,x1,y2,x2);

                checkX1=Math.min(x1,x2);
                checkX2=Math.max(x1,x2);
                checkY1=Math.min(y1,y2);
                checkY2=Math.max(y1,y2);


                if(x1!=x2)
                {
                    m=(y1-y2)/(x1-x2);
                    f=y1-(x1*m);
                }

                for(var k=0;k<noCluster;k++)
                { //Solving the equation of a straight line and cluster circle to obtain intersecting points

                    xsrc=centroidsData[k][0];
                    ysrc=centroidsData[k][1];
                    rad=radius[k];
                    

                    if(x1!=x2)
                    {
                        A=(m*m+1);
                        B=(2*m*f-2*xsrc-2*ysrc*m);
                        C=(xsrc*xsrc+ysrc*ysrc+f*f-rad*rad-2*ysrc*f);
                        root=B*B-4*A*C;

                        if(root>=0 && A!=0)
                        {
                            x=(-B+Math.sqrt(root))/(2*A);
                            y=m*x+f;
                            

                            if((checkX1<=x && x<=checkX2) && (checkY1<=y && y<=checkY2))
                            {
                                intersectPts[i][k].push({lng:x,lat:y,ind:j});
                            }

                            x=(-B-Math.sqrt(root))/(2*A);
                            y=m*x+f;
                            

                            if((checkX1<=x && x<=checkX2) && (checkY1<=y && y<=checkY2))
                            {
                                intersectPts[i][k].push({lng:x,lat:y,ind:j});
                            }
                        }
                    }
                    else
                    {
                        x=x1;
                        y=ysrc+Math.sqrt(rad*rad-((x1-xsrc)*(x1-xsrc)));


                        if((checkX1<=x && x<=checkX2) && (checkY1<=y && y<=checkY2))
                        {
                            intersectPts[i][k].push({lng:x,lat:y,ind:j});
                        }

                        y=ysrc-Math.sqrt(rad*rad-((x1-xsrc)*(x1-xsrc)));

                        if((checkX1<=x && x<=checkX2) && (checkY1<=y && y<=checkY2))
                        {
                            intersectPts[i][k].push({lng:x,lat:y,ind:j});
                        }
                    }
                }
            }

            for(var k=0;k<noCluster;k++)
            { //For each of the cluster calculating distance between two intersecting points

                dist[i][k]=0;
                if(intersectPts[i][k].length!=0)
                {
                    var len_intersectPts=intersectPts[i][k].length;
                    for(var l=1;l<len_intersectPts;l+=2)
                    {
                        var st=intersectPts[i][k][l-1]['ind'];
                        var end=intersectPts[i][k][l]['ind'];
                        dist[i][k]+=euclidean(intersectPts[i][k][l-1]['lng'],intersectPts[i][k][l-1]['lat'],flightPlanCordfn[i][st]['lng'],flightPlanCordfn[i][st]['lat']);

                        for(var itr=st+1;itr<end;itr++)
                          dist[i][k]+=euclidean(flightPlanCordfn[i][itr]['lng'],flightPlanCordfn[i][itr]['lat'],flightPlanCordfn[i][itr-1]['lng'],flightPlanCordfn[i][itr-1]['lat']);

                        dist[i][k]+=euclidean(intersectPts[i][k][l]['lng'],intersectPts[i][k][l]['lat'],flightPlanCordfn[i][end-1]['lng'],flightPlanCordfn[i][end-1]['lat']);
                    }

                    if(len_intersectPts==1)//If only one intersecting point then either the source is situated within
                    {                      //the cluster or the destination is situated within the cluster.

                        xsrc=centroidsData[k][0];
                        ysrc=centroidsData[k][1];
                        rad=radius[k];

                        x1=flightPlanCordfn[i][0]['lng'];
                        y1=flightPlanCordfn[i][0]['lat'];

                        if ((x1-xsrc)*(x1-xsrc)+(y1-ysrc)*(y1-ysrc)-rad*rad<=0)//Checking if the source is situated within the cluster using equation of circles
                            dist[i][k]+=euclidean(flightPlanCordfn[i][0]['lng'],flightPlanCordfn[i][0]['lat'],intersectPts[i][k][len_intersectPts-1]['lng'],intersectPts[i][k][len_intersectPts-1]['lat']);
                        else
                            dist[i][k]+=euclidean(flightPlanCordfn[i][len_i_th_route-1]['lng'],flightPlanCordfn[i][len_i_th_route-1]['lat'],intersectPts[i][k][len_intersectPts-1]['lng'],intersectPts[i][k][len_intersectPts-1]['lat']);
                    }
                }

            }
        }

//Distance of a route through a cluster calculation section end


//Cost calculation section start

        var cost=new Array(n);
        var eachRouteParam=new Array(n);//Concatenating the parameters considered for cost calculation. It has not been used though.
        var eachRouteTotalDist;
        var eachRouteTotalClusPts;
        var min;
        var minIndex;


        for(var i=0;i<n;i++)//For each route
        {
            cost[i]=0;
            eachRouteTotalDist=0;
            eachRouteTotalClusPts=0;
            for(var k=0;k<noCluster;k++)
            {
                if(dist[i][k]!=0)
                {
                    cost[i]+=(dist[i][k]*pointsInEachCluster[k]);
                    eachRouteTotalDist+=dist[i][k];
                    eachRouteTotalClusPts+=pointsInEachCluster[k];
                }
            }

            eachRouteParam[i]=new Array(eachRouteTotalDist,eachRouteTotalClusPts);

            //Calculating the minimum cost route.
            if(i==0)
            {
                min=cost[i];
                minIndex=i;
            }
            else
            {
                if(min>cost[i])
                {
                    min=cost[i];
                    minIndex=i;
                }
            }
        }

//Cost calculation section end



        //Loading the maps on the page
        window.onload=function ()
        {
          var s="<?php echo($_POST['source']) ?>";
          var d="<?php echo($_POST['destination']) ?>";
          document.getElementById("res").innerHTML+=("Source:"+s+"<br>");
          document.getElementById("res").innerHTML+=("Destination:"+d+"<br>");
      
          var color=["Red","Blue","Black","Green","Orange","Violet"];
          for(var i=0;i<n;i++)
          {
              document.getElementById("res").innerHTML+=("cost of route "+color[i]+": "+cost[i]+"<br>");
              document.getElementById("res").innerHTML+=("distance of route "+color[i]+": "+(distances_of_path[i])+" km<br>");
          }

          
          document.getElementById("res").innerHTML+=("Route with color "+color[minIndex]+" is the safest with a cost of "+min+".<br>");
        }
        
      </script>
    </head>

    <body >
      
      <nav class="navbar navbar-light bg-light" >
        
          <a class="navbar-brand" href="#">
            <img src="marker.png" width="40" height="40">
          </a>

          <div class="container">
            <form class="form-inline" method="POST" action="projectcode.php">

              <div class="form-group">
                <label for="source" class="col-md-4 col-form-label">Source</label>
                <div class="col-md-2">
                  <input type="text" class="form-control" name="source" id="source" placeholder="Eg. Kolkata, New York" autocomplete="off" required>
                </div>
              </div>

              <div class="form-group mx-5">
                <label for="destination" class="col-md-4 col-form-label">Destination</label>
                <div class="col-md-2">
                  <input type="text" class="form-control" name="destination" id="destination" placeholder="Eg. Kolkata, New York" autocomplete="off" required>
                </div>
              </div>
              
              <div class="form-group mx-5">
                <button class="btn btn-outline-success my-2 my-sm-2" type="submit" id="submitbtn" name="submitbtn">Search</button>
              </div>
            </form>
          </div>
      </nav>


      <div class="container border border-success rounded mt-3 bg-light" id="res"></div>
      <div class="container border border-success rounded mt-3" id="map"></div>
      
      
      <!-- Script for Jquery -->
      <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
      <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
      
    </body>
  </html>