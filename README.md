# Freemius Deploy

This GitHub Action deploys your WordPress plugin on Freemius. It uses the [Freemius PHP SDK](https://github.com/Freemius/freemius-php-sdk.git) and uses some functionality of [CodeAtCode/freemius-suite](https://github.com/CodeAtCode/freemius-suite)

## Arguments

| Argument       | Required | Function                                                                                                                                                                                                                                                                                        | Default |
| -------------- | -------- | ------- | ------- |
| `file_name`    | Yes      | File name of the to be uploaded wordpress plugin (zip extension).  _Note: the file has to be in the root folder of your repository_                                                                                                                                                                                                                                                      |         |
| `release_mode` | No       | `pending`, `beta`, or `released`. Set to beta to release the product to valid license holders that opted into the beta list. Set to released to release it to all valid license holders. When the product is released, it will be available for download right within the WP Admin dashboard. | `pending` |
| `version` | Yes | This is used to check whether the release is already uploaded. **Action will fail if the release has already been uploaded** | |
| `sandbox` | No | Whether to upload in sandbox mode | `false` |

## Environment variables

**Required**:

- `PUBLIC_KEY` (Developer Public Key)
- `DEV_ID`
- `SECRET_KEY` (Developer Secret Key)
- `PLUGIN_SLUG`
- `PLUGIN_ID`

Note: The PUBLIC_KEY and SECRET_KEY are your developer keys from Freemius, not the plugin-specific keys.

All these are found in your Freemius dashboard. You can find your public and secret keys, as well as your Dev ID under 
the keys section on the bottom of the 'My Profile' page. Your plugin slug and ID you can find on the products' settings 
page or in the SDK integration snippet.

_Tip: store these variables in your [secrets](https://help.github.com/en/actions/configuring-and-managing-workflows/creating-and-storing-encrypted-secrets)_

## Action outputs (since v0.1.1)

The action downloads both the **free** and **pro** versions and outputs their filenames as outputs:

- free_version
- pro_version

You can access these by setting an **id** to your workflow step. In a later step reference them like this: 

```
${{ steps.deploy_step_id.outputs.free_version }}
${{ steps.deploy_step_id.outputs.pro_version }}
```

These files can then be uploaded as artifacts or deployed to the WordPress SVN repository using actions like
[10up/action-wordpress-plugin-deploy](https://github.com/10up/action-wordpress-plugin-deploy).

## Example

```yml
- name: Deploy to Freemius
  id: freemius_deploy
  uses: buttonizer/freemius-deploy@v0.1.2
  with:
    file_name: my_wordpress_plugin.zip
    release_mode: pending
    version: 1.1.0
    sandbox: false
  env:
    PUBLIC_KEY: ${{ secrets.FREEMIUS_PUBLIC_KEY }}
    DEV_ID: 1234
    SECRET_KEY: ${{ secrets.FREEMIUS_SECRET_KEY }}
    PLUGIN_SLUG: my-wordpress-plugin
    PLUGIN_ID: 4321
```
