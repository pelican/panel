<?php

return [
    'daemon_connection_failed' => 'There was an exception while attempting to communicate with the daemon resulting in a HTTP/:code response code. This exception has been logged.',
    'node' => [
        'servers_attached' => 'A node must have no servers linked to it in order to be deleted.',
        'error_connecting' => 'Error connecting to :node',
        'not_viable' => 'The node :node does not have enough free resources to accept this server.',
        'daemon_off_config_updated' => 'The daemon configuration <strong>has been updated</strong>, however there was an error encountered while attempting to automatically update the configuration file on the Daemon. You will need to manually update the configuration file (config.yml) for the daemon to apply these changes.',
    ],
    'allocations' => [
        'server_using' => 'A server is currently assigned to this allocation. An allocation can only be deleted if no server is currently assigned.',
        'too_many_ports' => 'Adding more than 1000 ports in a single range at once is not supported.',
        'invalid_mapping' => 'The mapping provided for :port was invalid and could not be processed.',
        'cidr_out_of_range' => 'CIDR notation only allows masks between /25 and /32.',
        'port_out_of_range' => 'Ports in an allocation must be greater than or equal to 1024 and less than or equal to 65535.',
    ],
    'egg' => [
        'delete_has_servers' => 'An Egg with active servers attached to it cannot be deleted from the Panel.',
        'invalid_copy_id' => 'The Egg selected for copying a script from either does not exist, or is copying a script itself.',
        'has_children' => 'This Egg is a parent to one or more other Eggs. Please delete those Eggs before deleting this Egg.',
    ],
    'variables' => [
        'env_not_unique' => 'The environment variable :name must be unique to this Egg.',
        'reserved_name' => 'The environment variable :name is protected and cannot be assigned to a variable.',
        'bad_validation_rule' => 'The validation rule ":rule" is not a valid rule for this application.',
    ],
    'importer' => [
        'json_error' => 'There was an error while attempting to parse the JSON file: :error.',
        'file_error' => 'The JSON file provided was not valid.',
        'invalid_json_provided' => 'The JSON file provided is not in a format that can be recognized.',
    ],
    'subusers' => [
        'editing_self' => 'Editing your own subuser account is not permitted.',
        'user_is_owner' => 'You cannot add the server owner as a subuser for this server.',
        'subuser_exists' => 'A user with that email address is already assigned as a subuser for this server.',
    ],
    'databases' => [
        'delete_has_databases' => 'Cannot delete a database host server that has active databases linked to it.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'The maximum interval time for a chained task is 15 minutes.',
    ],
    'locations' => [
        'has_nodes' => 'Cannot delete a location that has active nodes attached to it.',
    ],
    'users' => [
        'is_self' => 'Cannot delete your own user account.',
        'has_servers' => 'Cannot delete a user with active servers attached to their account. Please delete their servers before continuing.',
        'node_revocation_failed' => 'Failed to revoke keys on <a href=":link">Node #:node</a>. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'No nodes satisfying the requirements specified for automatic deployment could be found.',
        'no_viable_allocations' => 'No allocations satisfying the requirements for automatic deployment were found.',
    ],
    'api' => [
        'resource_not_found' => 'The requested resource does not exist on this server.',
    ],
    'mount' => [
        'servers_attached' => 'A mount must have no servers attached to it in order to be deleted.',
    ],
    'server' => [
        'marked_as_failed' => 'This server has not yet completed its installation process, please try again later.',
        'file_too_large' => 'The file you are attempting to open is too large to view in the file editor.',
        'state_conflict' => 'This server is currently in an unsupported state, please try again later.',
        'suspended' => 'This server is currently suspended and the functionality requested is unavailable.',
        'maintenance' => 'The node of this server is currently under maintenance and the functionality requested is unavailable.',
        'restoring_backup' => 'This server is currently restoring from a backup, please try again later.',
        'transferring' => 'This server is currently being transferred to a new machine, please try again later.',
    ],
];
