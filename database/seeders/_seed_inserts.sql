INSERT INTO `agents` (`id`, `name`, `phone`, `updated_at`) VALUES
(11, 'Байкалова Любовь Викторовна ', '79232996393', NULL),
(12, 'Климова Марина', '79233006393', NULL),
(14, 'Карамашева Елена Сергеевна', '79233686393', NULL),
(21, 'Арыштаева Наталья Сергеевна', '79233909696', NULL),
(22, 'Руденко Владимир Анатольевич', '79233931023', NULL),
(23, 'Гобро Оксана Ивановна', '79233996969', NULL),
(24, 'Кенден Жанна Семис-ооловна', '79234521717', NULL),
(25, 'Бологова Татьяна Анатольевна', '79235484810', NULL),
(26, 'Гришилова Лариса Николаевна', '79235484819', NULL),
(28, 'Кудаярова Елена Шамильевна', '79235484834', NULL),
(29, 'Шарапова Ирина Игоревна', '79235484860', NULL),
(31, 'Куулар Юлия Михайловна', '79235484864', NULL),
(32, 'Бердникова Елена Викторовна', '79235484879', NULL),
(34, 'Куулар Елена Руслановна', '79235559924', NULL),
(35, 'Барби Маргарита Викторовна', '79235592044', NULL),
(37, ' Маланова Алдынай Анатольевна', '79235820017', NULL),
(40, 'Шеботкина Марина Александровна', '79235940017', NULL),
(43, ' Догуй-оол Елена Викторовна', '79293396969', NULL),
(46, 'Парахина Татьяна Алексеевна', '79339976393', NULL),
(47, 'Асминкина Юлия Михайловна', '79339986393', NULL),
(51, 'Лобода Анна Витальевна ', '79235836393', NULL),
(52, 'Щетинин Тимофей Дмитриевич', '79953742476', NULL),
(53, 'Бахман Евгения Альбертовна', '79992018138', NULL),
(58, 'Самохин Андрей Викторович', '90000000000', NULL);

INSERT INTO `offers` (`id`, `code`, `stage`, `status`, `offer_id`, `price`, `area`, `kitchen_area`, `living_area`, `city`, `location`, `agent_id`, `images`,
`deal`,
`category`,
`rooms`, `rooms_offered`, `floor`, `floors_total`, `commission`, `deposit`, `created_at`) VALUES
(1, '190-002', 2, 1, 53163, 18000, 26.00, 4.00, 12.00, 1, '{\"lat\": null, \"lng\": null, \"city\": \"Абакан\", \"address\": \"ул. Торосова, 7\"}', 38, '[\"https://abakan.brokerplus.ru/images/abakan/offer/53163/556cc25e9e8f48dc0c727aeab6bf7267.jpg\", \"https://abakan.brokerplus.ru/images/abakan/offer/53163/8872876665ebb7a5009583d9f43b6bea.jpg\", \"https://abakan.brokerplus.ru/images/abakan/offer/53163/a464b75ac9ec74d11c2c333f9db50db7.jpg\", \"https://abakan.brokerplus.ru/images/abakan/offer/53163/5b2219f5a014da3cbc3fdda8c582e1c4.jpg\"]', 3, 1, 1, 0, 0, 9, NULL, NULL, '2026-07-21 15:12:56'),
(2, '190-003', 2, 1, 53226, 6950000, 51.00, 10.00, 35.00, 2, '{\"lat\": null, \"lng\": null, \"city\": \"Кызыл\", \"address\": \"ул. Калинина, 24\"}', 35, '[\"https://kyzyl.brokerplus.ru/images/kyzyl/offer/53226/dfe758a04f96e498c650e184f376ebf0.jpg\", \"https://kyzyl.brokerplus.ru/images/kyzyl/offer/53226/d212cbcc2a6ae74d06c3e8336d5ffbe8.jpg\", \"https://kyzyl.brokerplus.ru/images/kyzyl/offer/53226/9cb7ccda8129bd424d0d2f54c8f5ba3a.jpg\", \"https://kyzyl.brokerplus.ru/images/kyzyl/offer/53226/f63506edd4e1f4611c3d4f899c85912b.jpg\"]', 1, 1, 2, 0, 0, 5, NULL, NULL, '2026-07-24 10:36:33');

INSERT INTO `vk_users` (`id`, `agent_id`, `vk_user_id`, `vk_token`, `email`, `is_token_available`, `first_name`, `last_name`, `screen_name`, `domain`, `deactivated`, `is_closed`, `can_access_closed`, `sex`, `bdate`, `relation`, `home_town`, `city_id`, `city_name`, `country_id`, `country_name`, `online`, `last_seen_at`, `last_seen_platform`, `followers_count`, `friend_status`, `status`, `verified`, `raw`) VALUES
(11, '11', '501508436', 'vk1.a.5SapWVgU6aMOfnE6jG58sm9Kji-aebcmMFQf8YuCnaeol1e3xVmZ6kfRgDdMOkDdyCBlkTNZXkuGCTEPJV2hcQ1vDw2BK8GmxlSHwnO2hT8vEFhGXDSFKooOckWkFNYKdOQ9WeJnB8MlqLfMfEcmbnYMfrcSxr9onWaguQL0P0V4j3eHjUjMO4BGQwTKByMmNxT7qXrCXjpaY1YiRrSS7A', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(12, '12', '509014431', 'vk1.a.pGEQ-Iog5FYNEjYZcBEWOWhhroojwU5EhM4yBEnHmLj758JMihWXtXPYbK4nWkq0xdVsGHb4MvNWxft1C_VGQAC-jeUw2CMXeR83Miticj-4KQ1B6Z9gcicjfAlys370LWW1RBZFgWCB3syNSjhKyqJnF2hGbHnmT70QZJ3lV8GDuSJO0ezdibxFYAYXcOdyg1BzqmzcWwVJ8KZvmcZB4A', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(14, '14', '491171224', 'vk1.a.y1oJXny3fjUYsHw0pftvCGdGBBtLsuOhc9IbGgpfS-w6q4i2Avq5n4MulrilVY30wHq1eLisULYYrxuplJ1plOGEbQA03k86zCcR37B0PVtPMgPka5nUtfUCyWBCj-E8QsZH-k1m0KCFxJ83kHpQka7Ml7jPZhI_XmrW0MPLbNP0w2MSKCoqg3rrDGlX_YGnu01KoXjozJfs3qBniWBGcA', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(21, '21', '550565478', 'vk1.a.aBkHBuNRmoY4MY46HUm1GgmtjzcPadoytLQADFlo-CufCkSg5jmWsqrKTO5rbj8FUY6JG-T2cghTT-nQlXMa1gq_EHLw255TUM-zrDAfHSj_IIe1D6iTeOvpGeSM0vpQDVaZyDK85BmwvsUJj8TyURjC-bos6feJJqrUn5HG2ucrjy6QHmmge9wtI4YsXf0rFApKXhpuZ_aB7EytIrjmTw', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(22, '22', '7622523', 'vk1.a.hREn4g35NTLguW1IyY9DHm84ShokBkvETFdGHvHdwwJcLPmnXsa8_onyG74D2oZW-vV0SrQWiVirmW5nDuSmCqgFJcLb7G18pHOGHSrqJznrfvd23obmkQkTYLZbPFDggtpZ1Yiov2Qc_nm2YPrRog0zGae7H3dnBARYph_HLduz_AJ4JBBYJU-w0mmqw6MWnTRjp1UCnkA40iWfNqur2g', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(23, '23', '651833287', 'vk1.a.0htc7y4Sv0Y1TDscaS1Gih3hG6YHGTrfEGw99hUxp0KS9-10r00xbSXZnTsNeO_pYXKKiyUtEaWGOXP7v2S0otF-bHpHJ8bN4irILTtix7AzWisWmBpcfNKs5OJ58pXGdJ2VgOSMignQK6ltvjWmALPt4AMnetAbnuqTlwxo1gara_WsUd-Q53fsAXtzKtWEFD7iC7ZWPoqKL535DkICuQ', NULL, 1, 'Оксана', 'Копань-Гобро', 'id651833287', 'id651833287', NULL, 0, 1, 1, '1978-10-22', 0, 'Абакан', 17, 'Абакан', NULL, NULL, 1, 1785226854, 7, 6876, 0, 'Ипотека | Юрист по недвижимости | Банкротства | Новостройки без денег', 0, '{\"id\": 651833287, \"sex\": 1, \"city\": {\"id\": 17, \"title\": \"Абакан\"}, \"bdate\": \"22.10.1978\", \"domain\": \"id651833287\", \"online\": 1, \"status\": \"Ипотека | Юрист по недвижимости | Банкротства | Новостройки без денег\", \"relation\": 0, \"verified\": 0, \"home_town\": \"Абакан\", \"is_closed\": false, \"last_name\": \"Копань-Гобро\", \"last_seen\": {\"time\": 1785226854, \"platform\": 7}, \"first_name\": \"Оксана\", \"online_app\": 6287487, \"screen_name\": \"id651833287\", \"friend_status\": 0, \"followers_count\": 6876, \"can_access_closed\": true}'),
(24, '24', '797296409', 'vk1.a.jXNyaf4shluxmCdqqAJnbmiYFAUQsIPPIgF1dWDAy8ClQ8Hz4shvzwU6QZPAEnWup-sznfw45-wlNFUGDv4Az_IOstuoXkrD8iZvILd1FT3oqVZQ1t40m59h4h0qDgP_mfoZ8TQOViJNzHDHYrSFnm-ODvSpsNPD3JPl32uGzX7RBKGGQOJODUstm0nVbWrk_E-IuFNjcMCSY7CSkSJqtA', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(25, '25', '475831156', 'vk1.a.KNGSM6qrJoK5jehoDg5WDhe45Fp4N0ca7PMkwZiHHQUFLbVu07iNWB8luo9qtEe4rFo0sTVKmMohEmZ-qsv3hRXo8ByLgHtY43q6Cf6vwLLOqTzbW-Na-LaAqefK0gQWrDDx0CRs2IH_SkTB2UKEQs3zlwXBxort82ReSCzVjbkS1nErbBXh7M853VQ4TslNSUHd4IBRMJ3pWGv33a36mQ', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(26, '26', '539961210', 'vk1.a.B5xymBADT3khamYGyEHHYpJecyPwMbtIhLIk10Tv5fcvxtUSaIaHR0_qGB8Wp3_e5jkvrMdW5MKQFGgNYIZm9Iipl6bvMMS_oANgI_x4ur8BGaoV49Ccf0_2w-2qyRmsC7X0etFFfq6Swh0G_EqSZZLySEr5MmAwDVcz7D3KrXq5VBBqTqFB2301H47ZftvgN96ZU7PpQbHn3_rsoX-Y4Q', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(28, '28', '461007215', 'vk1.a.CjAN1oC04rTk-53bm9ObM5D0-CmOZA5blOvW7BdibqA-qEFLVPEXa0rCH4W28W0NJhWqeZvgBdPzVT7DaheVkj3rWrUYJxYMW4fveNBLG-nZTybkTEGhRf7UOfBX84a3NYAykyJ6gThePGteKIEp26S-K1ejS9uo5NR1kB2jT453UQziw-VIlA1qYsFSVwFmlsDT1UHBNkz8oAmO6yvBFw', NULL, 1, 'Елена', 'Кудаярова', 'id461007215', 'id461007215', NULL, 0, 1, 1, '1969-01-23', 0, 'Кызыл', 76, 'Кызыл', NULL, NULL, 0, 1785217326, 4, 5864, 0, 'Риэлтор в Кызыле. Ипотека 6%.          ЖК Мөнгүн?️.    Продам вашу квартиру в Кызыле за 15 дней', 0, '{\"id\": 461007215, \"sex\": 1, \"city\": {\"id\": 76, \"title\": \"Кызыл\"}, \"bdate\": \"23.1.1969\", \"domain\": \"id461007215\", \"online\": 0, \"status\": \"Риэлтор в Кызыле. Ипотека 6%.          ЖК Мөнгүн🏗️.    Продам вашу квартиру в Кызыле за 15 дней\", \"relation\": 0, \"verified\": 0, \"home_town\": \"Кызыл\", \"is_closed\": false, \"last_name\": \"Кудаярова\", \"last_seen\": {\"time\": 1785217326, \"platform\": 4}, \"first_name\": \"Елена\", \"screen_name\": \"id461007215\", \"friend_status\": 0, \"followers_count\": 5864, \"can_access_closed\": true}'),
(29, '29', '305205759', 'vk1.a.hFJm5VvjqELXIhuQK2gAVxQgY7sc8DSI4rbwRijFRodEQMR3Km6MaB19NcObtBTtU5pP6BdlAY-pyXI0jGDmzGS3tNZAFO60dtzFtfNHGyv0APITxsVkoqNPo2FF8DzKkO_d9uhUAD7RPAXPsPOBxIGU6o0CQRx8dhi29pLNc9waBhrLCZD1hr0zXRDSpfMC8J6pz2jprJMciD09Zy9lGw', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(31, '31', '459255688', 'vk1.a.cpTEEc0lEyialACw33RNnBZfRB_vswAJT4NtUeX36saDipUTnYgcZq_ljwn0VzVykz5_EDeNarsq7Sax1kdMpNPiVY6GmiwuGtIOTTgHu1L6TcOdkTV37x5kr6Xq32blM64EmF4vbd-6ccfCAutvensVgVtSw6LA8s356W2AGE_qx5c8HJ5MRJ1mHqD_-dVBHaFyY-hTTlvKRtjZbfYulg', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(32, '32', '459256081', 'vk1.a.kBsS1hhBrfh0ZydfbmPysh8-oSvsKxAGGjB4r0DocLcnVOQAQmj5n8kwEDRZm7l4-HSc8MU-hRuOtK-tgvS6za7iElDpvAMgC7I0if_Gnk-5T5yZdkXOGXXipBl6AbT7pkLFjIrxduqDsb6OvmvXQc7dfN-IwXR-P2GmO63zlvTX_pcnIRzajbT4NBA1OUaoUP7u2rbH5VXHrGKjY5X39Q', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(34, '34', '37594247', 'vk1.a.Axy-IJ2155NpauSkpcLDPwYJw6h9Wt9sjktpK5Dc6HqjH9q6dDuf3EhuoKUstYq4Zj6-ptu9MFen4-RqGqwzch6pI_nn1S23O3OGky-cnxKWTrRsKASnO2Tve38GEpyn-S9VtOElzek5tUBfBeyfMD7kfNSCRg74uTjPQO8FeQL5YMwVPVJxZPjis6ycVFQORl7KSfRA1YmmaOZ6O5UFOQ', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(35, '35', '487898510', 'vk1.a.xxWLZmPuyZnQ_rjiJALnNEJwZs9pVrw667p1dt_UDKM13DiE-eRrqncUoY16DcuBMR6GYkCg5yQ5dWqkMh7fc9_CZ8qz0sxCfELqZ2_PB8DEoIQ5qZ6vNxyyepkNQOu668zTIxgcMMVwFunfkYDz-zaOp-T8PhUn_0BY_KjOsE3rh_6_KY95Inlwt692oUWVqJxMN7i9pnff6Mhxo0lISw', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(37, '37', '459121093', 'vk1.a.uLEvNI9qS5rK4r8Yussdt6_LovMPUckDqCo2oyeO5xuRiptoj8bJsCh0J2tyGWpValivqWr-bHLGOJiHxwxK1PXgtT9PWXc_7StHN8U4YDtzPV7yn-PmpHnOnVKnndS0g8Sgfxc1bfdrcbvPmaIsP2EbkXX4reydq5ZLtFXLFhz24nCUVNcmDtL67dk9BgaVikn2_AFEZJuWuQHRINgASA', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(40, '40', '461915287', 'vk1.a.TmuGkYc0clhzTYSVAQO720VjVfcRQFVPErTp3i72gYUWJZ37MKssUs8f1Cq7Mgq8rqDQnFOLKFUV4A2FC6Emn83qBAV6c7dXRFfmfYXcwII3r9d1zH2jqxDM-AGkaM4u5Vu1HOO_NcQD8x1vvkiceAX_VHhZQKjnSFV_WIkXhSE97mDlzADRSspXEA1OPGQxmZEVAJ93VjO3ysfwEC0MNA', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(43, '43', '631295899', 'vk1.a.NSYHGjljE40eBX4fxnqYIdvXfGQUN5GiGe-rKhy_Y6GodDzshgRUSY-orxKKtK462-9hDJjxj3NFMwrHnDnSXJNJ5tfTBeDk0jZN02qt8vH9YLuN3Q242phoQjnisX3SY-2q30hL6hHSfb3IOCtB1xi8-w33aD8ahbq9qCIAxQardRVqyysDR0xhe6KLdq-YnS0s2NocVwzZy9dq1S8kbg', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(46, '46', '796281427', 'vk1.a.-W24sQgdvK1DZzfDF6eqQWHKTCWIpNDnDnWqL0U_M6Lo5BcS4n_oeY0LkuoY3CJ131jIRWTeQcbC3Xrd2_CM4j_KJomcXz5VC5EYY1r62_LS6kyqVfCreKvu2JAAJc3WYTppci-KfCfEZEme8k0nVWJRVr2xsrDPWehW_szsiqp6PBTKlTYLZa0V-cn8Ei5t64l8nrFuu9FgfzrgumGtCw', NULL, 1, 'Татьяна', 'Парахина', 'id796281427', 'id796281427', NULL, 0, 1, 1, '1975-11-27', 4, 'Абакан', 17, 'Абакан', NULL, NULL, 0, 1785226961, 4, 12701, 0, '8 лет в сфере недвижимости ? Помогаю обрести дом вашей мечты. Каждая сделка - счастливые глаза клиентов ?', 0, '{\"id\": 796281427, \"sex\": 1, \"city\": {\"id\": 17, \"title\": \"Абакан\"}, \"bdate\": \"27.11.1975\", \"domain\": \"id796281427\", \"online\": 0, \"status\": \"8 лет в сфере недвижимости 🎯 Помогаю обрести дом вашей мечты. Каждая сделка - счастливые глаза клиентов 🏠\", \"relation\": 4, \"verified\": 0, \"home_town\": \"Абакан\", \"is_closed\": false, \"last_name\": \"Парахина\", \"last_seen\": {\"time\": 1785226961, \"platform\": 4}, \"first_name\": \"Татьяна\", \"screen_name\": \"id796281427\", \"friend_status\": 0, \"followers_count\": 12701, \"can_access_closed\": true}'),
(47, '47', '544990450', 'f734a28c2b206c11f7afd7f6a505ab539d1ec9a20889c151a93f99b731abaa1a4637987b868da6da1ac85', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(51, '51', '170494839', 'vk1.a.Qz-qfHE3J6arnsFQ1xtCWib4_YYu_TrR5YJpgmFN-TBksCSQmxHlZOXRsk87suLmm8A7y11-ww_td3_r6ysJDPAw7fXp6btKeq886Nr5KblGuxg8yImNGYaDTWd7OV9sbqeMd48w7Z2r-6VO0U1GDRPiucMGNsvQr2GnZmWSPkv8A-HRGHI4kZh07kL__yE7Q-LPkedAwW35pitFcSZiVQ', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(52, '52', '302572235', 'vk1.a.MLM6PpAefOPsMNG6ts0kna7fYE6KJ73Qq6EvlgkeL5uNkuDnuWOpabtNbn72vg-hoKwRhf9xpkal5Kh0vcDl4FUhlKiy4lUXlmpJyxxo3Vg8A09msxJU-g_H528V-Gugzv6pbnQYS0Pj2IenYo8y1TZeUnG0whPpmc1YBAowY_vgogtktrkZ3814K4T39Ad-1dp_xohrKN1KnCDc4tq9WA', NULL, 1, 'Тимофей', 'Щетинин', 'timowae', 'timowae', NULL, 0, 1, 2, '2002-11-25', 0, '', 17, 'Абакан', NULL, NULL, 0, 1785221873, 7, 300, 0, '', 0, '{\"id\": 302572235, \"sex\": 2, \"city\": {\"id\": 17, \"title\": \"Абакан\"}, \"bdate\": \"25.11.2002\", \"domain\": \"timowae\", \"online\": 0, \"status\": \"\", \"relation\": 0, \"verified\": 0, \"home_town\": \"\", \"is_closed\": false, \"last_name\": \"Щетинин\", \"last_seen\": {\"time\": 1785221873, \"platform\": 7}, \"first_name\": \"Тимофей\", \"screen_name\": \"timowae\", \"friend_status\": 0, \"followers_count\": 300, \"can_access_closed\": true}'),
(53, '53', '430803130', 'vk1.a.lX2mtxiGZEDWEnjyJavum0eociohXBcPrLttKPhy3UFEFwjFzNyisVu7wY9zJjkqkcYNEbcw2B79nAjUxmssKvv8WrRQKDuvBc3Itm23z3ovKQ4meo7_Lurg9DQKnsa88W77NKe742XDZEy-LuID0wbU-8IOJ4jQOFSAEWgGOX76zUQa0dxPCMjx4jG_CbPZz94qOcWUcq3_xMPwZX8cWQ', NULL, 1, 'Евгения', 'Бахман', 'id430803130', 'id430803130', NULL, 0, 1, 1, '1970-06-16', 4, '', 17, 'Абакан', NULL, NULL, 0, 1785226054, 2, 206, 0, 'Риэлтер  в Абакане. Быстро и честно помогу с недвижимостью', 0, '{\"id\": 430803130, \"sex\": 1, \"city\": {\"id\": 17, \"title\": \"Абакан\"}, \"bdate\": \"16.6.1970\", \"domain\": \"id430803130\", \"online\": 0, \"status\": \"Риэлтер  в Абакане. Быстро и честно помогу с недвижимостью\", \"relation\": 4, \"verified\": 0, \"home_town\": \"\", \"is_closed\": false, \"last_name\": \"Бахман\", \"last_seen\": {\"time\": 1785226054, \"platform\": 2}, \"first_name\": \"Евгения\", \"screen_name\": \"id430803130\", \"friend_status\": 0, \"followers_count\": 206, \"can_access_closed\": true}'),
(58, '58', '33352746', '66e3a046f15efd19cb52814ccb68e7cd774b7dc6aeb12d36657a6dede0a623ac8b930968916ff0eba713e', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL);

INSERT INTO `vk_groups` (`id`, `group_id`, `city`) VALUES
(1, 239430043, 1),
(2, 239430467, 1),
(3, 239430475, 2),
(4, 150813947, 1),
(5, 198187151, 1),
(6, 150818189, 1),
(7, 151303539, 1),
(8, 150100054, 1),
(9, 198982301, 1),
(10, 198982183, 1),
(11, 140897687, 1);

INSERT INTO `vk_post_stats` (`id`, `vk_post_id`, `views`, `datetime`) VALUES
(1, 1, 84, '2026-07-01 07:34:44'),
(2, 2, 73, '2026-07-01 07:34:44'),
(3, 1, 84, '2026-07-01 07:36:08'),
(4, 2, 73, '2026-07-01 07:36:08'),
(5, 1, 84, '2026-07-01 07:37:08'),
(6, 2, 73, '2026-07-01 07:37:08'),
(7, 1, 84, '2026-07-01 07:37:38'),
(8, 2, 73, '2026-07-01 07:37:38');

INSERT INTO `jobs` (`id`, `payload`, `status`, `created_at`, `available_at`, `processed_at`, `error`) VALUES
(1, '{\"class\":\"App\\\\Jobs\\\\CreateVkPostJob\",\"payload\":[1]}', 'pending', '2026-07-21 12:12:56', NULL, NULL, NULL),
(2, '{\"class\":\"App\\\\Jobs\\\\CreateVkPostJob\",\"payload\":[2]}', 'pending', '2026-07-24 07:36:33', NULL, NULL, NULL);

