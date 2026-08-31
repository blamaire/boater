<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
    <tr>
        <td style="border-left:3px solid #e12628; padding:4px 0 4px 16px; color:#374151; font-size:15px; font-style:italic; line-height:1.6;">
            {{ $content['text'] ?? '' }}
            @if (! empty($content['source']))
                <div style="margin-top:6px; color:#6b7280; font-size:13px; font-style:normal;">— {{ $content['source'] }}</div>
            @endif
        </td>
    </tr>
</table>
