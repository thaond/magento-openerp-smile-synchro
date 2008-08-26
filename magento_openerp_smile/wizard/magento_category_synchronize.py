# -*- encoding: utf-8 -*-
##############################################################################
#
# Copyright (c) 2008 Smile S.A. (http://www.smile.fr) All Rights Reserved.
# @authors: Sylvain Pamart, Rapha�l Valyi
#
# WARNING: This program as such is intended to be used by professional
# programmers who take the whole responsability of assessing all potential
# consequences resulting from its eventual inadequacies and bugs
# End users who are looking for a ready-to-use solution with commercial
# garantees and support are strongly adviced to contract a Free Software
# Service Company
#
# This program is Free Software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
#
##############################################################################

import wizard
import pooler
import xmlrpclib
import netsvc
from xml.parsers.expat import ExpatError


#===============================================================================
#    Information Form & Fields
#===============================================================================

_export_done_form = '''<?xml version="1.0"?>
<form string="Categories Synchronization">
    <separator string="Categories exported" colspan="4" />
    <field name="categ_new"/>
    <field name="categ_update"/>
</form>'''

_export_done_fields = {
    'categ_new': {'string':'New Categories', 'readonly': True, 'type':'integer'},
    'categ_update': {'string':'Updated Categories', 'readonly': True, 'type':'integer'},
}


def _do_export(self, cr, uid, data, context):
    
    #===============================================================================
    #  Init
    #===============================================================================
    categ_new = 0
    categ_update = 0
    logger = netsvc.Logger()
    pool = pooler.get_pool(cr.dbname)
    
    # Server communication
    magento_web_id=pool.get('magento.web').search(cr,uid,[('magento_id','=',1)])
    try:
        magento_web=pool.get('magento.web').browse(cr,uid,magento_web_id[0])
        server = xmlrpclib.ServerProxy("%sapp/code/community/Smile_OpenERP_Synchro/openerp-synchro.php" % magento_web.magento_url)# % website.url)
    except:
        raise wizard.except_wizard("UserError", "You must have a declared website with a valid URL! provided URL: %s/openerp-synchro.php" % magento_web.magento_url)
    
    #===============================================================================
    #  Product packaging
    #===============================================================================
    categ_ids = pool.get('product.category').search(cr, uid, [('exportable','=',True)]) #NB uid is the user id to enforce the rights accesses
    for category in pool.get('product.category').browse(cr, uid, categ_ids, context=context):
    
        
        path=''             #construct path
        magento_parent_id=1 #root catalog
        
        
        if(type(category.parent_id.id)== (int)): #if not root category
            
            last_parent=pool.get('product.category').browse(cr, uid, category.parent_id.id)
            magento_parent_id=last_parent.magento_id
            path= str(last_parent.magento_id)
            
            while(type(last_parent.parent_id.id) == (int)):
                
                last_parent=pool.get('product.category').browse(cr, uid, last_parent.parent_id.id)
                path=str(last_parent.magento_id)+'/'+path
                
        path='1/'+path
        path=path.replace("//","/")
        if path.endswith('/'): 
            path=path[0:-1]
        
        webcategory={
            'magento_id': category.magento_id or 0,
            'parent_id': magento_parent_id,
            'name': category.name,
            'path': path,
        }
         
        #===============================================================================
        #  Product upload to Magento
        #===============================================================================
        #print webcategory
        
        try:
            category_id = server.category_sync([webcategory])   
            
            # update Magento id in OpenERP or log error
            if category_id != 0 :
                if int(category.magento_id) == int(category_id):
                    categ_update += 1
                else:
                    categ_new += 1
                pool.get('product.category').write(cr, uid, category.id, {'magento_id': category_id})
            else:
                logger.notifyChannel("Magento Export", netsvc.LOG_ERROR, "Magento couldn't create or update the category ID %s , see your debug.xmlrpc.log in the Smile_OpenERP_Synch folder in your Apache!" % category.id)  
        except ExpatError, error:
            logger.notifyChannel("Magento Export", netsvc.LOG_ERROR, "Category ID %s has error ! See your debug.xmlrpc.log in the Smile_OpenERP_Synch folder in your Apache! \nError %s" %(category.id, error))
        

    return {'categ_new':categ_new, 'categ_update':categ_update }



#===============================================================================
#   Wizard Declaration
#===============================================================================

class wiz_magento_category_synchronize(wizard.interface):
    states = {
        'init': {
            'actions': [_do_export],
            'result': {'type': 'form', 'arch': _export_done_form, 'fields': _export_done_fields, 'state': [('end', 'End')] }
        }
    }
wiz_magento_category_synchronize('magento.categories.sync');
