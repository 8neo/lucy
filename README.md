# lucy
a file sharing/drop site with permanent retention inspired by pomf

## instructions for people without neurons
1. downlaqd envverythinq

2. extract static.zip and create a folder called "files" (this is where the files will be stored when uploaded)

3. open cmd in the project folder and run php -S localhost:80 (or you can put it into apache htdocs idk)

4. i'd HIGHLY recommend adding a dotfile (if ur doing the htdocs thing) where /files/ is restricted but not the actual files itself like ts):

`Options -Indexes

<FilesMatch ".*">
    Require all granted
</FilesMatch>`

(i totally didnt skid from chatgpt 4 seconds ago)
ye ye ok have fun
