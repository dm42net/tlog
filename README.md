# tlog
##New Structured Notetaking Tool

Over the years we’ve tried everything for taking notes – and seem to always end up back to using either a plain text document or even pencil and paper. All the tools like Evernote and Trello and the plethora of others, while feature rich, lack the basic simplicity of jotting things down. Over the years we’ve stretched and twisted various WIKI’s and even used spreadsheets to try to come up with the right mix of structured data, simplicity, and searchability.

Full text searching has it’s place, but it’s impossible to gather together all the notes related to a certain topic or from a certain time frame.

After some 20 years of playing with various solutions…. we now think we have something that covers all the bases.

This post gives some basic instructions on how it works.

##The Basic Concept

Remember in elementary school when you were taught to take notes on 3×5 index cards – one fact or idea or detail per card?  Then, you could easily re-order your cards as you were writing your paper.

That is the basic idea behind this tool.  It allows you to take short, simple notes while providing the tools to index and cross-reference those notes later.  It intends to do so with as little “fiddling” and as much flexibility as possible.

The twist is adding searchable tags that create an automatic index of your notes.

##TAGS

We’re all familiar with the “hashtag.”  Besides being useful as a way to make snarky comments about things, the “hashtag” is a useful tool to call attention to what’s important in a post or a comment and to cross reference items related to the same thing . . . mostly.The downside is that the symbol # doesn’t give any information about the significance of what follows it.  And this becomes a problem when trying to search.  Do a search on #1984 and your likely to not only get references to the book by George Orwell, but you will find references to events that happened in the year 1984.  With a simple search, there’s no way to know ahead of time what you will get.Now, imagine that references to the book were tagged   bk:1984  (bk: being a tag indicating that what follows is a book) and references to an event in that year was tagged  yr:1984 or dt:1984-07-04  (yr: being a tag for “year” or dt: being a tag for a “date”).Now suddenly things become easier to search and index.  If you want to find all the events that happened in 1984, you can search for “yr:1984”  or do a wildcard search “dt:1984*”.There are a million ways to use such tagging.For example, in the organizational system GETTING THINGS DONE(tm), David Allen suggests creating todo lists based on the context in which a task can be done.  So you may have one list for “errands”, another for “home,” and another for “office”.With our tagged note-taking system – adding a task to one of these lists is as easy as including “list:office” or “list:errands” somewhere in the note.  Is there some reason you want it to appear on a couple of different lists?  Perhaps “calls” and “office”, just include both.Want to group everything related to your “birthday party project” together?  Just tag everything with “project:birthdayparty”.Our note taking system will allow an unlimited number of tags and allows you to search them in any combination.  For example, when you want to sit down and make phone calls for the birthday party, you would simply search for “list:calls project:birthdayparty”. You can even sort based on the value of the tag.  So if you were working on a history project and you included a date tag for each event – for example – “Fall of the Berlin wall dt:1989-11-09,”  and “Fall of the building:WTC in place:NYC dt:2001-09-11” along with other dates, you could do something like “<~dt:%” and it would search for all your notes containing the “dt:” tag and sort them in ascending order.  You could sort them in descending order instead by searching for “>~dt:%”.Excluding items is as easy as putting a dash (“-“) before the search term.  So if you wanted to exclude all calls related to the birthdayparty project from your “calls” task list, you would simply search for “list:calls -project:birthdayparty”.In addition to creating a custom tag, the system also recognizes the “#” mark as a generic tag marker as well.

Adding Notes/Log Entries

Our mantra for adding notes was, “KEEP IT SIMPLE.”  The whole purpose of the tool is to get data in as quickly as possible.  No pretty formatting, just get the notes into the system.  The tagging of data is arbitrary and you can create any convention you wish.

Tags are simply a series of letters and numbers followed by a colon (“:”).  Anything that’s tagged with the same series of letters and numbers before the colon can be easily searched and grouped together.

To begin adding notes, you have to create a log to put them in.

New logs are easy to create.  Simply click on the “Log Options” button (the “cog” or “gear” in the top menu).  Then click on the “Add Log” (the “plus” symbol) once you get there.  Once you have added a log, you can add entries by going to the “home” or main screen (click the “house” on the top menu).

Once you’ve created a log.  You’re ready to begin adding notes.

The note entry screen has 4 fields.  (1) A selector for which log to store the note in.  (2) A “prefix” field, (3) A “Log Entry” field, and (4) a “suffix” field.

If you are adding multiple notes, the log selector as well as the prefix and suffix fields will keep their values when you click “Add”.  You only need to type the next note (unless you want to change them for some reason).

Once you have selected which log to update, you can just type your notes in the “Log Entry” field and click “Add”.  Your log item will be saved and the system will show you the last several entries.

Remember to keep your notes short – one thought or idea per note – and tag items you may want to cross reference in the future.

##Prefix/Suffix

Retyping the same tags over and over when you’re taking notes on the same things would be tedious and annoying.  That’s why there is a “prefix” and “suffix” field on the notetaking screen.  When you click the “Add” button, whatever was in the “prefix” and “suffix” fields will be carried over for your next note.

If you’re taking notes for a book report on George Orwell’s 1984, for example, it may be helpful to put “book:1984 chapter:2” in the “suffix” field and it will automatically include those tags when you click “Add”.  Now you can search for “book:1984 <~chapter:%” and it will sort your notes in chapter order.  If you included the page number by using a tag like “page:100”, you could search for “book:1984 <~page:%” and have all your notes sorted in page number order.  Or, if you were working on a section on the main character Wilson, you could search for “book:1984 character:Wilson”.

Tags only have meaning to you.  So you could use “bk:” instead of “book:” and “char:” instead of “character:” – or even just use single letters!  The trick is to be consistent.

##Multiple Notes

You can also enter multiple notes that all have the same prefix and suffix without clicking “Add” between each note.

To do so, simply include three dashses (“—“) all by themselves on a line between each note.  The three dashes (“—“)  must be the only thing on the line with no spaces in front and no spaces at the end.

If you’ve added a series of notes separated by three dashes on a line by themselves, each note will be added to the database separately and have the prefix and/or suffix attached just as if you clicked “Add” in between each.

This is a great feature to use if you don’t have online access!  Simply create a simple text document and take all your notes while you’re offline – separating each note with a line containing only three dashes.  When you can get online, you simply need to cut and paste the contents of your document into the add note screen and click “Add”.  All your notes will be added – and if there is anything in the Prefix or Suffix fields, that will also be added to each note!

##Searching for Entries

Fuller documentation on advanced search functionality is still under development.

The basic format is:  (sort order)(search type)(operator){tag}:{value}

Sort order:   <  = ascending,   > = descending

Search type:  ~ = wildcard,  ? = specific value.

Operator (for specific value searches only):    <  = Less than,  =  = Equal to,   >  = Greater than.

When using a wildcard search, include “%” in the value to represent zero or more unspecified characters.  So a search for  “~dt:1984%” would find all values beginning with 1984. A search for “~dt:%-10-%” would search for all dates in October of any year if the “dt:” tag is used for dates.

NOTE: Currently there is no “full text” searching available.  It is only possible to search for tagged items.  Full Text searching is being considered as part of future releases.

##Multiuser

Documentation on the multiuser functionality is still under development.

##Installation

1) Clone the git repository & install application dependencies using composer
```
git clone https://github.com/dm42net/tlog.git <install directory>
cd <install directory>
composer install
```

2) Create a MYSQL database to store logs and user data and assign MYSQL username/password

3) Edit config.php for your installation

4) Do initial configuration
```
CLI=true php -f index.php
```

5) run the `setup` command
```
<----------------------------------------->

 adduser - Add user to system
 setup - Set up Database and password secrets.
 reset - Delete all existing logs, Keep users and passwords.

---
Enter command (type 'quit' to exit): setup
```

6) Add an initial user
```
<----------------------------------------->

 adduser - Add user to system
 setup - Set up Database and password secrets.
 reset - Delete all existing logs, Keep users and passwords.

---
Enter command (type 'quit' to exit): adduser
Email: new@dm42.net
Password: mynewpw
Password(again): mynewpw
User added
```

7) Configure your web server to access `index.php` for all incoming requests
   for the URLHOST/URLPATHPREFIX you entered in `config.php`, eg. `https://my.web.host/path`

NGINX EXAMPLE LOCATION BLOCK with PHP-FPM using a socket interface:
```
    location ~ ^/path(.*) {
        root /path/to/webroot;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param SCRIPT_NAME index.php;
        fastcgi_pass  unix:/var/run/php-fpm/php-fpm.tlog.sock;
        fastcgi_index index.php;
    }
```

8) Open a browser and browse to `https://my.web.host/path/html`, enter your username and password
   and begin logging!
