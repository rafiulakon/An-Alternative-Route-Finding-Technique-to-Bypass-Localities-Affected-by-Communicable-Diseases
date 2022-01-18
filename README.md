# An-Alternative-Route-Finding-Technique-to-Bypass-Localities-Affected-by-Communicable-Diseases

#				Basic Info
This system finds alternative routes from source to destination bypassing localities affected by communicable diseases. This system has two components. One is clustering of
active infected cases which is done using python (clustering files). Another component is generating alternative routes and finding the safest one which is done using php and
javascript ( projectcode.php file ). All the data of active infected cases, clustering info, boundary data can be found in the data folder.
#				Run the program
To run the clustering and data generation scripts, any python interpreter can be used. To run the php file a server is needed. Apache server can be used to run the php script in
your local machine. To host apache server Xampp can be used. Xampp can be installed from the link: https://www.apachefriends.org/index.html .
After installing, within the Xampp folder there is a folder named "htdocs". All the php files that you want to run should be included in this folder. You can copy-paste a folder
(like Project Code folder) also.
After all the files are included in that folder follow the below steps to run the programs.
Steps to run the php files-> 
1. Start Xampp -> Start apache
2. Go to Google Chrome and type "localhost" -> It will show all the folders and files present in the htdocs folder.
3. Go to the php file and click to run it.
In this case - localhost -> Project Code Folder -> projectcode.php
