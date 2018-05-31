# SKYTranslator
Tool for managing XML files - from master to clone

# Usage
takes master and cloned xml and then stdouts fixed clone,
with all the elements in the same order, and with all
the missing CDATA prepended with !FIXME! copied from master

php SKYTranslator.php [master.xml] [cloned.xml] > [stdout|new_clone_fixed.xml]
 * master.xml is the master xml
 * cloned.xml is the xml which should be diffed against master

