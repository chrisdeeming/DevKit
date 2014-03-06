Xen Developer Tools
======

I am in the process of branching the excellent work by guiltar into a separate (new) add-on with some more features. I plan to integrate some of my existing add-ons into this, and some new tools I am hoping to build. I've long thought Guiltar's DevKit is absolutely invaluable for any developer. I now hope to make it even better and integrate some of my work into a single add-on.

Thank you guiltar :)

## NEW in Version 1.0 ##

* Integrates Add-on Builder with a new interface and new features, including:
    * Option to minify JS during build
    * Zips files using Zend Framework helper instead of an third party script
    * Ability to specify custom files that may be located in other directories
    * Ability to "Build and Download" which additionally forces your browser to download the built add-on
    * Changed default build directory to internal_data/addons/
* Refactored some code.
* Makes use of some new XF 1.2 features including template modifications and event hints.

### Upgrading from guiltar's DevKit ###

Due to the rebranding and extra features in this product, and multiple changes to various files, the best course of action for upgrading from guiltar's wonderful DevKit add-on is as follows:

* Uninstall DevKit from the Admin CP
* Delete all files and directores in the library/DevKit directory
* Install Xen Developer Tools as a new add-on