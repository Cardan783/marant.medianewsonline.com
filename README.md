![Static Badge](https://img.shields.io/badge/Version-V_1.0-green)
![Static Badge](https://img.shields.io/badge/Marant-Sampatv_like-blue)
![Static Badge](https://img.shields.io/badge/Marant%20es%20Electronica-8A2BE2)
![Static Badge](https://img.shields.io/badge/build-passing-brightgreen?logo=appveyor&label=Electronics)
![GitHub last commit (by committer)](https://img.shields.io/github/last-commit/google/skia)
![GitLab last commit](https://img.shields.io/gitlab/last-commit/gitlab-org%2Fgitlab)
![GitHub](https://img.shields.io/github/license/mashape/apistatus)

<h4 align="center">
:construction: Proyecto en construcción :construction:
</h4>

# Practica con Html5 Boostrap5 y Vue.js 2

## Implementado filtrado de temperatura presión y voltaje
> [!IMPORTANT]
> Gracias a Dios he logrado implementar el filtrado de dichos parámetros en la table.

## Implementado bordes redondeados y con color a la tabla
> [!IMPORTANT]
> en el archivo jsVue.js comente la llamada a la funcion de endTime y en su variable coloque la 
fecha 23:59 esto con el fin de que en la table me tome los valores de un dia completo.


https://sampatv.000webhostapp.com/Html5_Vue2_Boostrap_v5.2/get_data.php?start=2024-03-01%2020:00:00&end=2024-03-05%2020:23:59&temp=25&pre=2.5&volt=20

http://localhost/Html5_Vue2_Boostrap_v5.2/get_data.php?start=01-01-2024&end=25-03-2024&temp=25&pre=2.5&volt=20

SELECT * from sensores WHERE Fecha >= '01-01-2024' AND Fecha <= '28-01-2024' AND Temperatura >= '25' AND Presion <= '5' AND Voltaje >= '12' ORDER BY `No` DESC LIMIT 7200

SELECT * from sensores WHERE Fecha >= '01-11-2022' AND Fecha <= '28-11-2022' AND Temperatura >= '25' AND Presion <= '5' AND Voltaje >= '12' ORDER BY `No` DESC LIMIT 200;


SELECT * from dht_log WHERE date >= '2024-02-04' AND date <= '2024-02-07' ORDER BY `id` DESC LIMIT 7200

SELECT * FROM dht_log WHERE date BETWEEN '2024-02-05' AND '2024-02-10';

SELECT * FROM sensores WHERE Fecha BETWEEN '01-11-2022' AND '31-11-2022';

SELECT * FROM sensores WHERE Fecha BETWEEN '15-02-2023' AND '22-02-2023' ORDER BY `No` DESC


SELECT * FROM sensores WHERE Fecha BETWEEN '20-02-2023' AND '22-02-2023' AND Temperatura >= '25' AND Presion <= '5' AND Voltaje >= '12' ORDER BY `No` DESC LIMIT 7200;

SELECT * FROM sensores WHERE Fecha BETWEEN '20-02-2023' AND '22-02-2023' AND Temperatura >= '25' AND Presion <= '3' AND Voltaje >= '12' ORDER BY `No` DESC LIMIT 7200;


SELECT * from sensores WHERE Fecha >= '22-11-2022' AND Fecha <= '28-11-2022' AND Temperatura >= '25' AND Presion <= '5' AND Voltaje >= '12' ORDER BY `No` DESC LIMIT 200;


SELECT * from sensores WHERE Fecha >= '22-11-2022' AND Fecha <= '28-11-2022' AND Temperatura >= '25' AND Presion <= '3.5' AND Voltaje >= '12' ORDER BY `No` DESC LIMIT 200;

SELECT * from sensores WHERE Fecha >= '01-03-2024' AND Fecha <= '31-03-2024' AND Temperatura >= '25' AND Presion <= '5' AND Voltaje >= '12' ORDER BY `No` DESC LIMIT 7200

SELECT * from sensores WHERE Fecha >= '31-03-2024' AND Fecha <= '31-03-2024' AND Temperatura >= '24' AND Presion <= '6.5' AND Voltaje >= '12' ORDER BY `No` DESC LIMIT 200
