**Lingoes Converter**
=================

Introduction
------------
Lingoes Converter is a script written in PHP that can convert *.LD2/*.LDX dictionaries of [Lingoes](http://lingoes.net "Lingoes") into human-readable text files. The script is based on Xiaoyun Zhu analysis ([lingoes-extractor](http://code.google.com/p/lingoes-extractor/)) on the LD2/LDX dictionary format .

Requirements
------------
* PHP5 or higher
* Multibyte String extension enabled

Usage
-----

You can just download a binary distribution for Windows here and run it:

http://tiny.cc/lingoes-converter

Or if you are having a running webserver, upload the source and point your browser address to:

`http://yourwebsite/converter.php?input=path/to/somefile.ld2&encodingWord=UTF-8&encodingDef=UTF-16LE`

Or if you already have PHP downloaded / installed on your computer, issue this comand and follow the on-screen instruction:

`php converter.php`

Currently the class itself can't determine the encoding of the dictionary so let's just try to enter some of the encoding names to see what should work (mostly *UTF-8*, *UTF-16LE* or *UTF-16BE*).

About and License
-----------------
Copyright (c) 2013, WindyLea. All right reserved. Website : www.windylea.com

This project is made under BSD license. See LICENSE file for more information.