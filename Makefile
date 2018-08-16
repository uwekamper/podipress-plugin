VERSION = 0.0.6

pot:
	xgettext podipress/incl/class_podipress.php --keyword=__ -d podipress -o podipress/languages/podipress-td.pot

dist:
	zip -r podipress-${VERSION}.zip podipress -x "*/.*"
	unzip -l podipress-${VERSION}

clean:
	rm -f podipress.zip
	
