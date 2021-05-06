CardInfo.setDefaultOptions({
        banksLogosPath: 'dist/banks-logos/',
        brandsLogosPath: 'dist/brands-logos/'
      })

      $(function() {
        var $front = $('#front')
        var $bankLink = $('#bank-link')
        var $brandLogo = $('#brand-logo')
        var $number = $('#number')
        var $code = $('#code')
        var $random = $('#random')
        var $instance = $('#instance')
        var sendedPrefix = window.location.search.substr(1)

        $number.on('keyup change paste', function () {
          var cardInfo = new CardInfo($number.val())
          if (cardInfo.bankUrl) {
            $bankLink
              .attr('href', cardInfo.bankUrl)
              .css('backgroundImage', 'url("' + cardInfo.bankLogo + '")')
              .show()
          } else {
            $bankLink.hide()
          }
          $front
            .css('background', cardInfo.backgroundGradient)
            .css('color', cardInfo.textColor)
          $code.attr('placeholder', cardInfo.codeName ? cardInfo.codeName : '')
          $number.mask(cardInfo.numberMask)
          if (cardInfo.brandLogo) {
            $brandLogo
              .attr('src', cardInfo.brandLogo)
              .attr('alt', cardInfo.brandName)
              .show()
          } else {
            $brandLogo.hide()
          }
          $instance.html(JSON.stringify(cardInfo, null, 2))
        }).trigger('keyup')

        $random.on('click', function (e) {
          e.preventDefault()
          var aliases = Object.keys(CardInfo.banks)
          var alias = aliases[Math.floor(Math.random() * aliases.length)];
          var prefixes = Object.entries(CardInfo._prefixes)
          for (var i = prefixes.length; i; i--) {
            var j = Math.floor(Math.random() * i)
            var x = prefixes[i - 1]
            prefixes[i - 1] = prefixes[j]
            prefixes[j] = x
          }
          var prefix = prefixes.find(function (pair) {
            return (pair[1] === alias)
          })[0]
          $number
            .val($number.masked(prefix + '0000000000'))
            .trigger('keyup')
        })
      })
