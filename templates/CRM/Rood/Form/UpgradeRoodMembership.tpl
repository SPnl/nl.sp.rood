<div class="crm-form-block crm-search-form-block">
    <div class="crm-member_search">
        <h1>{ts}Upgrade rood lidmaatschappen{/ts}</h1>
        <table class="form-layout">
            <tr>
                <td><label>{ts}Rood lidmaatschap type{/ts}</label><br>
                    {$form.rood_mtype.html}
                </td>
                <td><label>{ts}SP lidmaatschap type{/ts}</label><br>
                    {$form.sp_mtype.html}
                </td>
            </tr>
            <tr>
                <td><label>{ts}Rood lidmaatschap Status{/ts}</label><br />
                    <div class="listing-box">
                        {foreach from=$form.member_status_id item="membership_status_val"}
                            <div class="{cycle values='odd-row,even-row'}">
                                {$membership_status_val.html}
                            </div>
                        {/foreach}
                    </div>
                </td>
                <td></td>
            </tr>
            <tr><td><label>{ts}Leden met geboortedatum tussen{/ts}</label></td><td></td></tr>
            <tr>
                <td>{include file="CRM/common/jcalendar.tpl" elementName=birth_date_from}</td>
                <td>{include file="CRM/common/jcalendar.tpl" elementName=birth_date_to}</td>
            </tr>
            <tr>
                <td>{ts}Minimale lidmaatschapsbijdrage (per kwartaal){/ts}</td><td></td>
            </tr><tr>
                <td>{$form.minimum_fee.html}</td><td></td>
            </tr>
            <tr>
                <td><label>{ts}Beeindig Rood met lidmaatschap status{/ts}</label><br>
                    {$form.rood_mstatus.html}
                </td>
                <td></td>
            </tr>

            {if (isset($found))}
                <tr>
                    <td>
                        Er zijn {$found} lidmaatschappen gevonden die voldoen aan de selectie voor een upgrade. <br>
                        Weet u het zeker?
                        <input type="hidden" name="continue" value="1" />
                    </td>
                    <td></td>
                </tr>
            {/if}

            <tr>
                <td colspan="2">{include file="CRM/common/formButtons.tpl"}</td>
            </tr>
        </table>
    </div>
</div>