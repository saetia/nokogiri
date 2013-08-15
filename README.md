```php
<?php

#load html
$Nokogiri = Nokogiri::HTML(file_get_contents('http://apple.com'));

#find and remove all h1 elements
$Nokogiri->css('h1')->delete();

#print the html with h1 elements removed
echo $Nokogiri->to_html();
echo $Nokogiri;

#find the first A element
$a = $Nokogiri->at_css('a');

#print the html within that tag
echo $Nokogiri->inner_html($a);

#display the text
echo $a->content;

#change the text 
$a->content = 'new string';

#get the text (sub elements)
echo $a->text; //Your Price: $49.99

#use regex to parse out just the price
echo $a->text('/[\d\.]+/'); //49.99

#show the href="" attribute on the A tag
echo $a['href'];

#change the href="" attribute
$a['href'] ='http://newurl.com';

#remove the href="" attribute
unset($a['href']);

#show all attributes
print_r($a->attributes);

?>
```
