STACK Evaluation for the ILIAS ExtendedTestStatistics Plugin
============================================================

Copyright (c) 2017-2024 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

- Authors:   Fred Neumann <fred.neumann@ili.fau.de>, Jesus Copado <jesus.copado@ili.fau.de>


Requirements
------------

This is an add-on to the ExtendedTestStatistics for ILIAS to provide a detailed evaluation of STACK questions. You need to install these plugin to use it:
* https://github.com/ilifau/ExtendedTestStatistics
* https://github.com/surlabs/STACK

Installation
------------

When you download the add-on as ZIP file from GitHub, please rename the extracted directory to *StackEvaluation*
(remove the branch suffix, e.g. -master).

Copy the StackEvaluation directory to your ILIAS installation at the followin path
(create subdirectories, if neccessary): 

`Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ExtendedTestStatistics/addons`

The evaluation is automatically recognized by the ExtendedTestStatistics plugin and shown in its plugin
administration. Choose here if it should be available to platform administrators or all users.

Purpose
-------

The main purpose of this evaluation is to a detailed evaluation of the aswers given to STACK questions regarding their potential response trees. 
