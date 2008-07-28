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

#===============================================================================
#    Information Form & Fields
#===============================================================================

_export_done_form = '''<?xml version="1.0"?>
<form string="Product and Stock Synchronization">
    <separator string="Products exported" colspan="4" />
    <field name="prod_new"/>
    <field name="prod_update"/>
</form>'''

_export_done_fields = {
    'prod_new': {'string':'New products', 'readonly': True, 'type':'integer'},
    'prod_update': {'string':'Updated products', 'readonly': True, 'type':'integer'},
}


def _do_export(self, cr, uid, data, context):
    
    #===============================================================================
    #  Init
    #===============================================================================

    prod_new = 0
    prod_update = 0
    logger = netsvc.Logger()
    pool = pooler.get_pool(cr.dbname)
    prod_ids = pool.get('product.product').search(cr, uid, [('exportable','=',True)]) #NB uid is the user id to enforce the rights accesses
    
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
    for product in pool.get('product.product').browse(cr, uid, prod_ids, context=context):
    
        webproduct={
            'magento_id': product.magento_id or int(0),
            'product_id': product.id or int(0),
            'name': product.name or '',
            'quantity': product.virtual_available or int(0),
            'price' : product.list_price or float(0.0), 
            'weight': product.weight_net or float(0.0), 
            'category_id': product.categ_id.magento_id or int(0), 
            'category_name': product.categ_id.magento_name or '',
            'description' : product.description or 'Auto description',
            'sale_description' : product.description_sale or 'Auto short description',
            'tax_class_id': product.magento_tax_class_id or 0,
        }
        
        
        #===============================================================================
        #  Product upload to Magento
        #===============================================================================
        
        try:
            updated_magento_id = server.product_sync([webproduct])   
            
            # update Magento id in OpenERP or log error
            if updated_magento_id != 0 :
                if int(product.magento_id) == int(updated_magento_id):
                    prod_update += 1
                else:
                    prod_new += 1
                pool.get('product.product').write(cr, uid, product.id, {'magento_id': updated_magento_id})
            else:
                logger.notifyChannel("Magento Export", netsvc.LOG_ERROR, "product ID %s unknown !" % webproduct['product_id'])  
        except:
            logger.notifyChannel("Magento Export", netsvc.LOG_ERROR, "product ID %s has error !" % webproduct['product_id']) 
        

    return {'prod_new':prod_new, 'prod_update':prod_update}



#===============================================================================
#   Wizard Declaration
#===============================================================================

class wiz_magento_product_synchronize(wizard.interface):
    states = {
        'init': {
            'actions': [_do_export],
            'result': {'type': 'form', 'arch': _export_done_form, 'fields': _export_done_fields, 'state': [('end', 'End')] }
        }
    }
wiz_magento_product_synchronize('magento.products.sync');
